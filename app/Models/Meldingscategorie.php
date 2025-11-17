<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meldingscategorie extends Model
{
    protected $table = 'meldingscategorien';   // let op naam
    protected $fillable = ['naam'];

    public function soorten()
    {
        // FK op meldingssoorten.meldingscategorieId
        return $this->hasMany(Meldingssoort::class, 'meldingscategorieId', 'id');
    }
}
