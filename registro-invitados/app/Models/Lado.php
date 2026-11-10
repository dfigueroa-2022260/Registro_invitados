<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Letra de cada fila de la sala (superior, inferior, izquierdo). */
class Lado extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'lados';
    protected $primaryKey = 'lado';
    protected $keyType = 'string';
    protected $fillable = ['lado', 'letra'];
}
