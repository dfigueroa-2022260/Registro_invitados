<?php

namespace App\Support;

/** Acceso a la distribución de la sala definida en config/sala.php. */
class Distribucion
{
    public static function grupos(): array
    {
        return config('sala.grupos');
    }

    /** ['central', 'superior', 'inferior', 'izquierdo'] */
    public static function lados(): array
    {
        return array_keys(self::grupos());
    }

    public static function numeros(string $lado): array
    {
        return self::grupos()[$lado]['numeros'] ?? [];
    }

    public static function nombre(string $lado): string
    {
        return self::grupos()[$lado]['nombre'] ?? $lado;
    }

    public static function letrasPredeterminadas(): array
    {
        return array_map(fn ($g) => $g['letra'], self::grupos());
    }

    /** Para el mapa: ['central' => ['nombre' => ..., 'numeros' => [...]], ...] */
    public static function paraMapa(): array
    {
        return array_map(fn ($g) => ['nombre' => $g['nombre'], 'numeros' => array_values($g['numeros'])], self::grupos());
    }

    /** Todos los asientos de la sala, en el orden en que se asignan los invitados: [[lado, numero], ...]. */
    public static function espacios(): array
    {
        $espacios = [];
        foreach (self::grupos() as $lado => $g) {
            foreach ($g['numeros'] as $numero) {
                $espacios[] = [$lado, $numero];
            }
        }

        return $espacios;
    }
}
