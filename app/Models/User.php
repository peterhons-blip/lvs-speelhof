<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'schoolid',
        'groupId',

        'name',       // achternaam of volledige naam (zoals jij het gebruikt)
        'voornaam',

        'gebruikersnaam',
        'smartschool_gebruikersnaam',

        'email',
        'password',
        'allowed_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'allowed_login' => 'boolean',

        // belangrijk voor verjaardag-checks
        'geboortedatum' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'schoolid', 'id');
    }

    /**
     * Optioneel: alias zodat je net als bij Leerling makkelijk ->achternaam kan gebruiken
     */
    public function getAchternaamAttribute(): ?string
    {
        return $this->getAttribute('name');
    }

    /**
     * Helper voor verjaardags-check (voor je nieuwe nachtelijke command).
     */
    public function isJarigVandaag(): bool
    {
        if (!$this->geboortedatum) {
            return false;
        }

        return $this->geboortedatum->isBirthday(now('Europe/Brussels'));
    }
}
