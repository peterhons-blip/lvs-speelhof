<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Leerling;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LeerlingenWorden18;
use App\Services\SmartschoolSoap;

class CheckLeeftijdLeerlingen extends Command
{
    protected $signature = 'leerlingen:check-18 {--test}';
    protected $description = 'Controleer welke leerlingen vandaag 18 worden';

    public function handle(): int
    {
        $this->info('Check gestart…');
         $isTest = $this->option('test') === true;


        // Laad eventueel relation 'klas' mee; harmless als die niet bestaat
        $leerlingen = Leerling::with('klas')->get();
        $leerlingenVandaag18 = [];

        foreach ($leerlingen as $leerling) {

            if (! method_exists($leerling, 'wordtVandaag18')) {
                $this->error('Methode wordtVandaag18() bestaat niet op het Leerling-model.');
                Log::error('Methode wordtVandaag18() ontbreekt op Leerling.');
                return self::FAILURE;
            }

            if ($leerling->wordtVandaag18()) {
                // Klasnaam normaliseren: kolom -> JSON -> relation -> string -> fallback
                $klasNaam =
                    $leerling->klasnaam                           // plain kolom op leerlingen
                    ?? ($leerling->klas['klasnaam'] ?? null)       // JSON kolom 'klas' => ['klasnaam'=>...]
                    ?? ($leerling->klas?->klasnaam ?? null)        // relation 'klas' -> klasnaam
                    ?? (is_string($leerling->klas) ? $leerling->klas : null)
                    ?? 'onbekend';

                $msg = "{$leerling->voornaam} {$leerling->naam} (klas {$klasNaam}) wordt vandaag 18 ({$leerling->geboortedatum->format('Y-m-d')})";
                Log::info($msg);
                $this->line($msg);

                $leerlingenVandaag18[] = [
                    'voornaam'          => $leerling->voornaam ?? '',
                    'naam'              => $leerling->naam ?? '',
                    'klas'              => $klasNaam,
                    'geboortedatum'     => $leerling->geboortedatum, // Carbon (cast in model)
                    'gebruikersnaam'    => $leerling->gebruikersnaam,   // ← Smartschool gebruikersnaam
                ];
            }
        }

        if (count($leerlingenVandaag18) === 0) {
            Log::info('Geen leerlingen worden vandaag 18.');
            $this->info('Geen leerlingen worden vandaag 18.');
            return self::SUCCESS;
        }

        if ($isTest) {
            $this->warn('TESTMODUS: geen mail of Smartschoolberichten verzonden.');
            Log::info('TESTMODUS: leerlingen:check-18 heeft geen mail/Smartschoolberichten verzonden.');
            return self::SUCCESS;
        }

        // 1. Mail naar team
        //Mail::to('peter.hons@atheneumsinttruiden.be')
         //   ->cc(['pascale.liebens@atheneumsinttruiden.be ', 'stijn.forier@atheneumsinttruiden.be '])
         //   ->send(new LeerlingenWorden18($leerlingenVandaag18));
       // Log::info('Mail verzonden met ' . count($leerlingenVandaag18) . ' leerling(en).');
        //$this->info('Mail verzonden.');

        // 2. Smartschool-berichten naar leerlingen
        /** @var SmartschoolSoap $ss */
        $ss = app(SmartschoolSoap::class);

        $onderwerp = "Proficiat met je 18de verjaardag! 🌟 Belangrijk: aanpassing van je co-accounts!";

        foreach ($leerlingenVandaag18 as $ll) {

            if (empty($ll['gebruikersnaam'])) {
                Log::warning("Geen Smartschool gebruikersnaam voor {$ll['voornaam']} {$ll['naam']}");
                continue;
            }

            // Body genereren via Blade-view
            $body = view('smartschool.berichten.wordt18', [
                'voornaam' => $ll['voornaam'],
                'naam'     => $ll['naam'],
            ])->render();

            // Afzender uit .env halen
             $userIdentifier = env('SMARTSCHOOL_SENDER_USER');

             try {
                $ss->sendMessage(
                    $ll['gebruikersnaam'],  // userIdentifier = Smartschool gebruikersnaam
                    $onderwerp,
                    $body,
                    $userIdentifier,         
                    false                    // copyToLVS
                );

                Log::info("Smartschoolbericht verzonden naar {$ll['voornaam']} {$ll['naam']} ({$ll['gebruikersnaam']})");

                // 🔻 HIER: co-accounts uitzetten
                $ss->disableCoAccounts($ll['gebruikersnaam']);
                Log::info("Co-accounts uitgeschakeld voor {$ll['voornaam']} {$ll['naam']} ({$ll['gebruikersnaam']})");
                $this->info("Co-accounts uitgeschakeld voor {$ll['voornaam']} {$ll['naam']} ({$ll['gebruikersnaam']})");

            } catch (\SoapFault $e) {
                Log::error("Fout bij versturen Smartschoolbericht of uitschakelen co-accounts voor {$ll['gebruikersnaam']}: ".$e->getMessage());
                $this->error("Fout bij Smartschoolactie voor {$ll['gebruikersnaam']}: ".$e->getMessage());
            }
        }

        $this->info('Smartschoolberichten verzonden.');
        return self::SUCCESS;
    }
}
