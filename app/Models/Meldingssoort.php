<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meldingssoort extends Model
{
    protected $table = 'meldingssoorten';
    protected $fillable = ['meldingscategorieId','naam','omschrijving','warn_after'];

    public function categorie()
    {
        return $this->belongsTo(Meldingscategorie::class, 'meldingscategorieId', 'id');
    }

    public function meldingen()
    {
        return $this->hasMany(Melding::class, 'meldingssoortId', 'id');
    }
}
