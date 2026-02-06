<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $table = 'scholen';

    protected $fillable = [
        'schoolnaam',
        'instellingsnummer',
        'smartschooladres',
        'ontvangers_overzicht_18',
        'ontvangers_overzicht_18_ssaccount',
        'smartschool_verzender',
        'smartschool_verzender_secretariaat',
        'smartschool_verzender_beleid',
    ];


    public function leerlingen(): HasMany
    {
        return $this->hasMany(Leerling::class, 'schoolid');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'schoolid', 'id');
    }

    public function ontvangersOverzicht18(): array
    {
        // verwacht CSV of puntkomma gescheiden lijst
        $raw = (string) ($this->ontvangers_overzicht_18 ?? '');
        $parts = preg_split('/[;,]+/', $raw);

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
