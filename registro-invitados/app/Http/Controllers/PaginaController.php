<?php

namespace App\Http\Controllers;

use App\Models\Asiento;
use App\Services\SalaService;
use Illuminate\Http\Request;

class PaginaController extends Controller
{
    public function __construct(private SalaService $sala)
    {
    }

    public function login()
    {
        return view('paginas.login', ['empresas' => $this->sala->empresasConInvitados()]);
    }

    /**
     * Asiento y persona (titular o suplente) del invitado en sesión; si ya no existe en la lista,
     * se cierra su sesión de invitado. Devuelve [Asiento, nombre de la persona].
     */
    private function invitadoActual(Request $request): ?array
    {
        $id = $request->session()->get('invitado_id');
        $rol = $request->session()->get('invitado_rol', SalaService::TITULAR);
        $asiento = $id ? Asiento::find($id) : null;
        $persona = $asiento ? $this->sala->persona($asiento, $rol) : '';
        if (! $asiento || $persona === '') {
            $request->session()->forget(['invitado_id', 'invitado_rol', 'invitado_firmo']);

            return null;
        }

        return [$asiento, $persona];
    }

    public function firma(Request $request)
    {
        if (! $this->invitadoActual($request)) {
            return redirect()->route('login');
        }

        return view('paginas.firma');
    }

    public function asiento(Request $request)
    {
        $actual = $this->invitadoActual($request);
        if (! $actual) {
            return redirect()->route('login');
        }
        [$asiento, $persona] = $actual;
        if (! $request->session()->get('invitado_firmo')) {
            return redirect('/firma');
        }

        return view('paginas.asiento', ['datos' => [
            'invitado' => [
                'nombres' => $persona,
                'empresaNombre' => $asiento->empresa,
                'asiento' => $this->sala->ref($asiento, $this->sala->letras()),
            ],
        ]]);
    }

    public function admin(Request $request)
    {
        if (! $request->session()->get('es_admin')) {
            return redirect()->route('login');
        }

        return view('paginas.admin');
    }
}
