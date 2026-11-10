<?php

/*
 * Distribución de la sala según el plano. Los números son los que están impresos en cada mesa
 * (posiciones físicas fijas), por eso no se renumeran al agregar o quitar asientos.
 * La posición de cada silla en el mapa se calcula en public/js/mapa.js a partir de estos números.
 * La letra es la predeterminada de cada mesa; el administrador puede cambiarla desde el mapa.
 */
return [
    'grupos' => [
        'central' => ['nombre' => 'Mesa central', 'letra' => 'A', 'numeros' => range(1, 23)],
        'superior' => ['nombre' => 'Mesa superior', 'letra' => 'B', 'numeros' => range(24, 33)],
        'inferior' => ['nombre' => 'Mesa inferior', 'letra' => 'C', 'numeros' => range(34, 43)],
        'izquierdo' => ['nombre' => 'Mesa izquierda', 'letra' => 'D', 'numeros' => range(1, 7)],
    ],
];
