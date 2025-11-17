<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Leerling;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LeerlingenWorden18;

class CheckLeeftijdLeerlingen extends Command
{
    protected $signature = 'leerlingen:check-18 {--test}';
    protected $description = 'Controleer welke leerlingen vandaag 18 worden';

    public function handle(): int
    {
        $this->info('Check gestart…');

        // Laad eventueel relation 'klas' mee; harmless als die niet bestaat
        $leerlingen = Leerling::with('klas')->get();

        $leerlingenVandaag18 = [];

        foreach ($leerlingen as $leerling) {
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
                    'voornaam'      => $leerling->voornaam ?? '',
                    'naam'          => $leerling->naam ?? '',
                    'klas'          => $klasNaam,
                    'geboortedatum' => $leerling->geboortedatum, // Carbon (cast in model)
                ];
            }
        }

        if (count($leerlingenVandaag18) === 0) {
            Log::info('Geen leerlingen worden vandaag 18.');
            $this->info('Geen leerlingen worden vandaag 18.');
            return self::SUCCESS;
        }

        // Alleen mailen als er iets te melden is.
        Mail::to('peter.hons@atheneumsinttruiden.be')
            ->cc(['pascale.liebens@atheneumsinttruiden.be ', 'stijn.forier@atheneumsinttruiden.be '])
            ->send(new LeerlingenWorden18($leerlingenVandaag18));


        Log::info('Mail verzonden met ' . count($leerlingenVandaag18) . ' leerling(en).');
        $this->info('Mail verzonden.');

        return self::SUCCESS;
    }
}
