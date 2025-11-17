<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Klas extends Model
{
    protected $table = 'klassen';
    public $timestamps = true;
    protected $fillable = ['klasnaam'];

    public function leerlingen()
    {
        return $this->hasMany(Leerling::class, 'klasid', 'id');
    }
}
