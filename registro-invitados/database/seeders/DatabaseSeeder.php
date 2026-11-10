<?php

namespace Database\Seeders;

use App\Models\Asiento;
use App\Models\Lado;
use App\Support\Distribucion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea la sala del plano (config/sala.php) y carga unos invitados de ejemplo (database/data/sala.seed.json)
 * en los primeros asientos. Solo rellena lo que está vacío.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Lado::count() === 0) {
            foreach (Distribucion::letrasPredeterminadas() as $lado => $letra) {
                Lado::create(['lado' => $lado, 'letra' => $letra]);
            }
        }

        if (Asiento::count() === 0) {
            $datos = json_decode((string) file_get_contents(database_path('data/sala.seed.json')), true);
            $personas = array_values(array_filter($datos['asientos'], fn ($a) => $a['nombres'] !== ''));

            $filas = [];
            foreach (Distribucion::espacios() as $i => [$lado, $numero]) {
                $p = $personas[$i] ?? null;
                $filas[] = [
                    'lado' => $lado,
                    'numero' => $numero,
                    'nombres' => $p['nombres'] ?? '',
                    'documento' => $p['documento'] ?? '',
                    'empresa' => $p['empresa'] ?? '',
                    'categoria' => $p['categoria'] ?? '',
                    'firmado' => false,
                ];
            }
            DB::table('asientos')->insert($filas);
        }
    }
}
