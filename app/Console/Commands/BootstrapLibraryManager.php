<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BootstrapLibraryManager extends Command
{
    protected $signature = 'bibliotheek:eerste-beheerder {gebruikersnaam : Smartschool-gebruikersnaam van de ICT-coördinator}';
    protected $description = 'Registreer de allereerste ICT-beheerder via een vertrouwde lokale terminal';

    public function handle(): int
    {
        if (! Schema::hasTable('portal_staff')) {
            $this->error('Tabel portal_staff ontbreekt. Voer eerst de gebruikersmigratie uit.');
            return self::FAILURE;
        }

        $username = mb_strtolower(trim((string) $this->argument('gebruikersnaam')));
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,119}$/D', $username)) {
            $this->error('Ongeldige Smartschool-gebruikersnaam.');
            return self::FAILURE;
        }

        if (! $this->confirm("Eerste ICT-beheerder '{$username}' aanmaken?", false)) {
            $this->warn('Geannuleerd.');
            return self::FAILURE;
        }

        if (DB::table('portal_staff')->exists()) {
            $this->error('Gebruikersbeheer is al geïnitialiseerd. Beheer rollen via de website.');
            return self::FAILURE;
        }

        DB::table('portal_staff')->insert([
            'smartschool_username' => $username,
            'display_name' => null,
            'role' => 'ict_coordinator', 'is_active' => 1,
            'changed_by' => 'console:bootstrap',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info("Eerste ICT-beheerder '{$username}' aangemaakt.");
        return self::SUCCESS;
    }
}
