<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Models\Asiento;
use App\Services\SalaService;
use Illuminate\Http\Request;

class FirmaController extends Controller
{
    private const PREFIJO_PNG = 'data:image/png;base64,';

    public function __construct(private SalaService $sala)
    {
    }

    public function guardar(Request $request)
    {
        $id = $request->session()->get('invitado_id');
        if (! $id) {
            throw new ApiError('Primero regístrate para consultar', 401);
        }

        $firma = $request->input('firma');
        if (! is_string($firma) || ! str_starts_with($firma, self::PREFIJO_PNG)) {
            throw new ApiError('La firma debe ser una imagen PNG', 400);
        }
        if (strlen($firma) > 1_500_000) {
            throw new ApiError('La firma es demasiado grande', 400);
        }

        $rol = $request->session()->get('invitado_rol', SalaService::TITULAR);
        $asiento = Asiento::find($id);
        $persona = $asiento ? $this->sala->persona($asiento, $rol) : '';
        if (! $asiento || $persona === '') {
            throw new ApiError('El invitado no existe', 404);
        }

        $base64 = substr($firma, strlen(self::PREFIJO_PNG));
        $imagen = base64_decode($base64, true);
        if ($imagen === false || strlen($imagen) < 4 || substr($imagen, 0, 4) !== "\x89PNG") {
            throw new ApiError('La firma no es una imagen PNG válida', 400);
        }

        // La firma queda en la base de datos (Base64), no en una carpeta.
        $this->sala->marcarFirmado($asiento, $base64, $rol);
        $request->session()->put('invitado_firmo', true);

        return response()->json(['asiento' => $this->sala->ref($asiento->refresh(), $this->sala->letras())], 201);
    }
}
