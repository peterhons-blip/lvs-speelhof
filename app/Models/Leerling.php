<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leerling extends Model
{
    protected $table = 'leerlingen';
    public $timestamps = true;
    protected $fillable = ['gebruikersnaam','naam','voornaam','klasid','geboortedatum'];
    protected $casts = [
        'geboortedatum' => 'date',
    ];

    public function klas()
    {
        return $this->belongsTo(Klas::class, 'klasid', 'id');
    }

    public function wordtVandaag18(): bool
    {
        return $this->geboortedatum->addYears(18)->isToday();
    }

    public function wordtBinnen7Dagen18(): bool
    {
        if (!$this->geboortedatum) return false;

        $dag18 = $this->geboortedatum->copy()->addYears(18)->toDateString();
        $doeldag = now('Europe/Brussels')->addDays(7)->toDateString();

        return $dag18 === $doeldag;
    }

    // 'achternaam' alias voor kolom 'naam'
    public function getAchternaamAttribute()
    {
        return $this->getAttribute('naam');
    }

    // 'email' alias voor kolom met dash: `e-mailadres`
    public function getEmailAttribute()
    {
        return $this->getAttribute('e-mailadres');
    }

    public function school()
{
    return $this->belongsTo(\App\Models\School::class, 'schoolid');
}
}
