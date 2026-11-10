<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asiento extends Model
{
    public $timestamps = false;

    protected $table = 'asientos';

    protected $fillable = [
        'lado', 'numero', 'nombres', 'documento', 'empresa', 'categoria',
        'suplente_nombres', 'suplente_documento',
        'firmado', 'fecha_firma', 'firma_por',
    ];

    protected $casts = [
        'numero' => 'integer',
        'firmado' => 'boolean',
        'fecha_firma' => 'datetime',
    ];
}
