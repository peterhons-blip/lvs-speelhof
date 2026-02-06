<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmartschoolSoap;
use App\Models\Leerling;
use App\Models\User;
use Carbon\Carbon;

class CheckVerjaardagen extends Command
{
    protected $signature = 'verjaardagen:check
                            {--test : Testmodus: niets verzenden, enkel loggen}
                            {--date= : Forceer datum (YYYY-MM-DD) om te testen}
                            {--only= : Filter: leerlingen|users}
                            {--to= : Test-override: stuur alles naar deze Smartschool gebruikersnaam}
                            {--dummy : Gebruik 2 dummy personen (1 leerling + 1 user) voor test}';

    protected $description = 'Stuurt elke dag Smartschool verjaardagsbericht naar jarige leerlingen en leerkrachten (zonder dubbels voor 18-jarigen).';

    public function handle(): int
    {
        $isTest = $this->option('test') === true;
        $only = strtolower((string)($this->option('only') ?? ''));
        $toOverride = trim((string)($this->option('to') ?? ''));
        $useDummy = $this->option('dummy') === true;

        /** @var SmartschoolSoap $ss */
        $ss = app(SmartschoolSoap::class);

        $today = $this->option('date')
            ? Carbon::parse($this->option('date'), 'Europe/Brussels')->startOfDay()
            : now('Europe/Brussels')->startOfDay();

        $this->info('Verjaardagen-check: ' . $today->toDateString());

        // Onderwerp
        $subject = "Proficiat met je verjaardag! 🎉";

        // =========================
        // Dummy mode (handig testen)
        // =========================
        if ($useDummy) {
            $this->warn('DUMMY: gebruikt dummy personen (geen DB).');

            $dummyPayloads = [
                [
                    'type' => 'leerling',
                    'voornaam' => 'Test',
                    'naam' => 'Leerling',
                    'school' => null,
                    'recipient' => $toOverride ?: 'dummy.leerling',
                    'age' => 17,
                ],
                [
                    'type' => 'user',
                    'voornaam' => 'Test',
                    'naam' => 'Leerkracht',
                    'school' => null,
                    'recipient' => $toOverride ?: 'dummy.leerkracht',
                    'age' => 33,
                ],
            ];

            foreach ($dummyPayloads as $p) {
                $bodyView = $p['type'] === 'leerling'
                    ? $this->pickView('smartschool.berichten.default.verjaardag_leerling', 'smartschool.berichten.default.verjaardag_leerling')
                    : $this->pickView('smartschool.berichten.default.verjaardag_user', 'smartschool.berichten.default.verjaardag_user');

                $body = view($bodyView, [
                    'voornaam' => $p['voornaam'],
                    'naam'     => $p['naam'],
                    'age'      => $p['age'],
                    'school'   => $p['school'],
                    'datum'    => $today,
                ])->render();

                if ($isTest) {
                    $this->line("TEST: zou sturen naar {$p['recipient']} (dummy {$p['type']})");
                    continue;
                }

                try {
                    // sender: we nemen standaard "verzender" uit env (of je kan dit later per school maken)
                    $sender = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';

                    $ss->sendMessage($p['recipient'], $subject, $body, $sender, false, 0);
                    $this->info("DUMMY: bericht verstuurd naar {$p['recipient']}");
                } catch (\SoapFault $e) {
                    $this->error("DUMMY: fout bij versturen naar {$p['recipient']}: " . $e->getMessage());
                }
            }

            return self::SUCCESS;
        }

        // =========================
        // Leerlingen
        // =========================
        if ($only === '' || $only === 'leerlingen') {
            $leerlingen = Leerling::with('school')
                ->whereMonth('geboortedatum', $today->month)
                ->whereDay('geboortedatum', $today->day)
                ->get();

            foreach ($leerlingen as $l) {
                // Niet dubbel: als vandaag 18 → skip (want leerlingen:check-18 doet al de “18-flow”)
                $age = $l->geboortedatum ? Carbon::parse($l->geboortedatum, 'Europe/Brussels')->age : null;
                if ($age === 18) {
                    Log::info("VERJAARDAG: skip leerling {$l->id} ({$l->gebruikersnaam}) → wordt vandaag 18 (handled by leerlingen:check-18)");
                    continue;
                }

                $recipient = $toOverride !== '' ? $toOverride : (string)($l->gebruikersnaam ?? '');
                if (trim($recipient) === '') {
                    Log::warning("VERJAARDAG: leerling {$l->id} heeft geen gebruikersnaam → overgeslagen.");
                    continue;
                }

                // Template per school mogelijk (later uitbreidbaar)
                $view = $this->pickView(
                    $l->school ? "smartschool.berichten.scholen.{$l->school->id}.verjaardag_leerling" : '',
                    "smartschool.berichten.default.verjaardag_leerling"
                );

                $body = view($view, [
                    'voornaam' => $l->voornaam,
                    'naam'     => $l->naam,
                    'age'      => $age,
                    'school'   => $l->school,
                    'datum'    => $today,
                ])->render();

                if ($isTest) {
                    $this->line("TEST: leerling {$l->voornaam} {$l->naam} → {$recipient}");
                    continue;
                }

                // Afzender: per school (beleid/verzender) of fallback env
                $sender = $this->pickSenderForSchool($l->school);

                try {
                    $ss->sendMessage($recipient, $subject, $body, $sender, false, 0);
                    Log::info("VERJAARDAG: leerling bericht verstuurd naar {$recipient} | sender={$sender}");
                    $this->info("Leerling: verstuurd naar {$recipient}");
                } catch (\SoapFault $e) {
                    Log::error("VERJAARDAG: fout leerling {$recipient}: " . $e->getMessage());
                    $this->error("Leerling: fout naar {$recipient}: " . $e->getMessage());
                }
            }
        }

        // =========================
        // Users (leerkrachten)
        // =========================
        if ($only === '' || $only === 'users') {
            $users = User::with('school')
                ->whereMonth('geboortedatum', $today->month)
                ->whereDay('geboortedatum', $today->day)
                ->get();

            foreach ($users as $u) {
                $age = $u->geboortedatum ? Carbon::parse($u->geboortedatum, 'Europe/Brussels')->age : null;

                // Extra safety: ook bij users skip als 18 (moet niet, maar voorkomt ooit dubbele flows)
                if ($age === 18) {
                    Log::info("VERJAARDAG: skip user {$u->id} → wordt vandaag 18 (safety)");
                    continue;
                }

                $recipient = $toOverride !== '' ? $toOverride : (string)($u->smartschool_gebruikersnaam ?? '');
                if (trim($recipient) === '') {
                    Log::warning("VERJAARDAG: user {$u->id} heeft geen smartschool_gebruikersnaam → overgeslagen.");
                    continue;
                }

                $view = $this->pickView(
                    $u->school ? "smartschool.berichten.scholen.{$u->school->id}.verjaardag_user" : '',
                    "smartschool.berichten.default.verjaardag_user"
                );

                $body = view($view, [
                    'voornaam' => $u->voornaam ?: '',
                    'naam'     => $u->name ?: '',
                    'age'      => $age,
                    'school'   => $u->school,
                    'datum'    => $today,
                ])->render();

                if ($isTest) {
                    $this->line("TEST: user {$u->voornaam} {$u->name} → {$recipient}");
                    continue;
                }

                $sender = $this->pickSenderForSchool($u->school);

                try {
                    $ss->sendMessage($recipient, $subject, $body, $sender, false, 0);
                    Log::info("VERJAARDAG: user bericht verstuurd naar {$recipient} | sender={$sender}");
                    $this->info("User: verstuurd naar {$recipient}");
                } catch (\SoapFault $e) {
                    Log::error("VERJAARDAG: fout user {$recipient}: " . $e->getMessage());
                    $this->error("User: fout naar {$recipient}: " . $e->getMessage());
                }
            }
        }

        $this->info('Klaar.');
        return self::SUCCESS;
    }

    private function pickView(string $preferred, string $fallback): string
    {
        if ($preferred !== '' && view()->exists($preferred)) return $preferred;
        return $fallback;
    }

    private function pickSenderForSchool($school): string
    {
        // Jij kan dit sturen “van beleid” of “van verzender”.
        // Voor verjaardagen: ik neem beleid als dat ingevuld is, anders verzender, anders env.
        $sender = $school ? trim((string)($school->smartschool_verzender_beleid ?? '')) : '';
        if ($sender === '' && $school) $sender = trim((string)($school->smartschool_verzender ?? ''));
        if ($sender === '') $sender = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';
        return $sender;
    }
}
