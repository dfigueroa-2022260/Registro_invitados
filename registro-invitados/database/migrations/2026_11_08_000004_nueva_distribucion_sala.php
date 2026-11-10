<?php

use App\Support\Distribucion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pasa una base de datos con la sala anterior (3 filas) a la distribución del plano (4 mesas, 50 asientos).
 * Los invitados ya cargados se conservan (con su firma, suplente, etc.) y se reubican en orden en los
 * primeros asientos; los asientos vacíos viejos se reemplazan por los nuevos. Revisa dónde quedó cada uno
 * y ajústalo desde el admin o con una importación.
 * En una instalación nueva no hace nada: el seeder crea la sala.
 */
return new class extends Migration
{
    public function up(): void
    {
        $yaMigrada = DB::table('lados')->where('lado', 'central')->exists();
        $vacia = ! DB::table('lados')->exists() && ! DB::table('asientos')->exists();
        if ($yaMigrada || $vacia) {
            return;
        }

        DB::transaction(function () {
            $letras = DB::table('lados')->pluck('letra', 'lado')->all();
            $personas = DB::table('asientos')->where('nombres', '!=', '')->get()
                ->sort(fn ($a, $b) => strcmp((string) ($letras[$a->lado] ?? 'Z'), (string) ($letras[$b->lado] ?? 'Z')) ?: ($a->numero <=> $b->numero))
                ->values();

            $espacios = Distribucion::espacios();
            $conservar = [];
            $sinLugar = [];
            foreach ($personas as $i => $p) {
                if (! isset($espacios[$i])) {
                    $sinLugar[] = (array) $p;
                    continue;
                }
                [$lado, $numero] = $espacios[$i];
                DB::table('asientos')->where('id', $p->id)->update(['lado' => $lado, 'numero' => $numero]);
                $conservar[] = $p->id;
            }

            if ($sinLugar) {
                // No caben en la sala nueva: se guardan aparte para no perderlos.
                file_put_contents(storage_path('app/invitados-sin-asiento.json'), json_encode($sinLugar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                Log::warning('Invitados sin asiento en la nueva distribución: '.count($sinLugar).' (ver storage/app/invitados-sin-asiento.json)');
            }

            DB::table('asientos')->whereNotIn('id', $conservar)->delete();

            $faltantes = array_map(fn ($e) => ['lado' => $e[0], 'numero' => $e[1]], array_slice($espacios, count($conservar)));
            foreach (array_chunk($faltantes, 50) as $lote) {
                DB::table('asientos')->insert($lote);
            }

            DB::table('lados')->delete();
            foreach (Distribucion::letrasPredeterminadas() as $lado => $letra) {
                DB::table('lados')->insert(['lado' => $lado, 'letra' => $letra]);
            }
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás: la distribución anterior ya no existe.
    }
};
