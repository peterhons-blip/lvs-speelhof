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
