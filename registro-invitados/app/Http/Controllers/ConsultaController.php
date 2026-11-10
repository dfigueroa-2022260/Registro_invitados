<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Models\Asiento;
use App\Services\SalaService;
use App\Support\Distribucion;
use Illuminate\Http\Request;

class ConsultaController extends Controller
{
    public function __construct(private SalaService $sala)
    {
    }

    /** Datos actuales de quien consulta (su asiento puede haber cambiado si el administrador lo editó). */
    public function yo(Request $request)
    {
        $id = $request->session()->get('invitado_id');
        $rol = $request->session()->get('invitado_rol', SalaService::TITULAR);
        $asiento = $id ? Asiento::find($id) : null;
        $persona = $asiento ? $this->sala->persona($asiento, $rol) : '';
        if (! $asiento || $persona === '') {
            throw new ApiError('Tu registro ya no existe en la lista de invitados', 404);
        }

        return response()->json([
            'id' => $asiento->id,
            'nombres' => $persona,
            'empresaNombre' => $asiento->empresa,
            'asiento' => $this->sala->ref($asiento, $this->sala->letras()),
        ]);
    }

    /** Forma de la sala y estado de cada asiento, sin nombres de personas. */
    public function mapa()
    {
        $letras = $this->sala->letras();
        $asientos = $this->sala->ordenados($letras)->map(fn ($a) => [
            'id' => $a->id,
            'lado' => $a->lado,
            'numero' => $a->numero,
            'etiqueta' => $this->sala->etiqueta($a, $letras),
            'estado' => $a->firmado ? 'asistio' : ((string) $a->nombres !== '' ? 'asignado' : 'libre'),
        ])->all();

        return response()->json(['letras' => $letras, 'grupos' => Distribucion::paraMapa(), 'asientos' => $asientos]);
    }

    /** Busca invitados por nombre, empresa o número de asiento (ej. A-5). */
    public function buscar(Request $request)
    {
        $consulta = $request->query('q');
        $consulta = is_string($consulta) ? mb_substr(trim($consulta), 0, 60) : '';
        $q = $this->sala->normalizar($consulta);
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $letras = $this->sala->letras();
        $resultados = $this->sala->ordenados($letras)
            ->filter(fn ($a) => (string) $a->nombres !== ''
                && str_contains($this->sala->normalizar($this->sala->etiqueta($a, $letras).' '.$a->nombres.' '.$a->suplente_nombres.' '.$a->empresa), $q))
            ->take(8)
            ->map(fn ($a) => [
                'id' => $a->id,
                'lado' => $a->lado,
                'numero' => $a->numero,
                'etiqueta' => $this->sala->etiqueta($a, $letras),
                'nombres' => $a->nombres,
                'empresa' => (string) $a->empresa,
                'suplente' => (string) $a->suplente_nombres,
                'firmado' => (bool) $a->firmado,
            ])
            ->values()
            ->all();

        return response()->json($resultados);
    }
}
