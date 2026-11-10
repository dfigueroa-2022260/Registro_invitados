<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Imagen de la firma de un asiento, en Base64 (PNG). Va en su propia tabla para no cargarla al listar asientos. */
class Firma extends Model
{
    public $timestamps = false;

    protected $table = 'firmas';

    protected $fillable = ['asiento_id', 'imagen'];
}
