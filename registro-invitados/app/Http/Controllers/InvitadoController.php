<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Services\SalaService;
use App\Support\Entrada;
use Illuminate\Http\Request;

class InvitadoController extends Controller
{
    public function __construct(private SalaService $sala)
    {
    }

    public function verificar(Request $request)
    {
        $nombres = Entrada::texto($request->input('nombres'), 120, 3, 'Escribe tus nombres y apellidos');
        $empresa = Entrada::texto($request->input('empresa'), 120, 1, 'Selecciona tu empresa o asociación');

        $encontrado = $this->sala->buscarInvitado($nombres, $empresa);
        if (! $encontrado) {
            throw new ApiError('No encontramos tus datos en la lista de invitados. Revisa tu nombre y la empresa seleccionada.', 404);
        }

        $request->session()->regenerate();
        ['asiento' => $asiento, 'rol' => $rol] = $encontrado;
        $request->session()->put('invitado_id', $asiento->id);
        $request->session()->put('invitado_rol', $rol);
        $request->session()->forget('invitado_firmo');

        // El asiento se muestra hasta el último paso, después de firmar.
        return response()->json([
            'id' => $asiento->id,
            'nombres' => $this->sala->persona($asiento, $rol),
            'empresaNombre' => $asiento->empresa,
            'rol' => $rol,
        ]);
    }

    /** Cierra la sesión de invitado (botones "Regresar" y "Salir"). */
    public function salir(Request $request)
    {
        $request->session()->forget(['invitado_id', 'invitado_rol', 'invitado_firmo']);

        return response()->noContent();
    }
}
