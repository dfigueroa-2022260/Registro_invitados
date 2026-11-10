<?php

namespace App\Support;

use App\Exceptions\ApiError;

/** Validación sencilla de los datos de entrada (responde 400 con el primer error, como el backend original). */
class Entrada
{
    /** Texto recortado. $min/$max en caracteres. */
    public static function texto(mixed $valor, int $max, int $min = 0, ?string $msgMin = null, ?string $msgMax = null, bool $opcional = false): string
    {
        if ($valor === null && $opcional) {
            return '';
        }
        if (! is_string($valor)) {
            throw new ApiError($msgMin ?? 'Datos inválidos', 400);
        }
        $valor = trim($valor);
        if (mb_strlen($valor) < $min) {
            throw new ApiError($msgMin ?? 'Datos inválidos', 400);
        }
        if (mb_strlen($valor) > $max) {
            throw new ApiError($msgMax ?? "El texto no puede superar $max caracteres", 400);
        }

        return $valor;
    }

    public static function lado(mixed $valor): string
    {
        if (! is_string($valor) || ! in_array($valor, Distribucion::lados(), true)) {
            throw new ApiError('Datos inválidos', 400);
        }

        return $valor;
    }

    public static function numero(mixed $valor): int
    {
        if (! is_int($valor) && ! (is_string($valor) && ctype_digit($valor))) {
            throw new ApiError('Datos inválidos', 400);
        }

        return (int) $valor;
    }

    public static function letra(mixed $valor): string
    {
        $letra = self::texto($valor, 2, 1, 'Escribe una letra', 'Máximo 2 caracteres');
        if (! preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñ0-9]+$/u', $letra)) {
            throw new ApiError('Solo letras o números', 400);
        }

        return mb_strtoupper($letra);
    }
}
