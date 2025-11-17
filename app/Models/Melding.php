<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Melding extends Model
{
    protected $table = 'meldingen';
    protected $fillable = ['leerlingId','meldingssoortId','comment'];

    public function leerling()
    {
        return $this->belongsTo(Leerling::class, 'leerlingId', 'id');
    }

    public function soort()
    {
        return $this->belongsTo(Meldingssoort::class, 'meldingssoortId', 'id');
    }

    // Handig: $melding->categorie->naam
    public function getCategorieAttribute()
    {
        return optional($this->soort)->categorie;
    }
}
