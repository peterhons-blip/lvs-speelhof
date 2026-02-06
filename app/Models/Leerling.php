<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Leerling extends Model
{
    protected $table = 'leerlingen';
    public $timestamps = true;

    protected $fillable = [
        'gebruikersnaam',
        'naam',
        'voornaam',
        'klasid',
        'schoolid',
        'geboortedatum',
    ];

    protected $casts = [
        'geboortedatum' => 'date',
    ];

    public function klas(): BelongsTo
    {
        return $this->belongsTo(Klas::class, 'klasid', 'id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'schoolid', 'id');
    }

    public function wordtVandaag18(): bool
    {
        if (!$this->geboortedatum) {
            return false;
        }

        // copy() zodat geboortedatum niet gemuteerd wordt
        return $this->geboortedatum->copy()->addYears(18)->isToday();
    }

    public function wordtBinnen7Dagen18(): bool
    {
        if (!$this->geboortedatum) {
            return false;
        }

        // Ook hier copy() om mutatie te vermijden
        $dag18 = $this->geboortedatum->copy()->addYears(18)->toDateString();
        $doeldag = now('Europe/Brussels')->addDays(7)->toDateString();

        return $dag18 === $doeldag;
    }

    // 'achternaam' alias voor kolom 'naam'
    public function getAchternaamAttribute(): ?string
    {
        return $this->getAttribute('naam');
    }

    // 'email' alias voor kolom met dash: `e-mailadres`
    public function getEmailAttribute(): ?string
    {
        return $this->getAttribute('e-mailadres');
    }
}
