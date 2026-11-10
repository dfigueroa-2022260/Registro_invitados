<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Services\ExportarService;
use App\Services\ImportarService;
use App\Services\SalaService;
use App\Support\Entrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AdminController extends Controller
{
    public function __construct(private SalaService $sala)
    {
    }

    /** Contraseña de administrador; limita los intentos fallidos por IP (8 cada 10 minutos). */
    public function login(Request $request)
    {
        $password = Entrada::texto($request->input('password'), 200, 1, 'Escribe la contraseña');
        $clave = 'admin-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, 8)) {
            throw new ApiError('Demasiados intentos. Espera unos minutos e inténtalo de nuevo.', 429);
        }
        if (! hash_equals((string) config('registro.admin_password'), $password)) {
            RateLimiter::hit($clave, 600);
            throw new ApiError('Contraseña incorrecta', 401);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();
        $request->session()->put('es_admin', true);

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('es_admin');

        return response()->noContent();
    }

    public function sala()
    {
        return response()->json($this->sala->listarAdmin());
    }

    public function agregar(Request $request)
    {
        return response()->json($this->sala->agregar(Entrada::lado($request->input('lado')), Entrada::numero($request->input('numero'))), 201);
    }

    public function editar(Request $request, int $id)
    {
        $datos = [
            'nombres' => Entrada::texto($request->input('nombres'), 120, opcional: true),
            'documento' => Entrada::texto($request->input('documento'), 40, opcional: true),
            'empresa' => Entrada::texto($request->input('empresa'), 120, opcional: true),
            'categoria' => Entrada::texto($request->input('categoria'), 60, opcional: true),
            'suplente_nombres' => Entrada::texto($request->input('suplenteNombres'), 120, opcional: true),
            'suplente_documento' => Entrada::texto($request->input('suplenteDocumento'), 40, opcional: true),
        ];

        return response()->json($this->sala->editar($id, $datos));
    }

    public function eliminar(int $id)
    {
        $this->sala->eliminar($id);

        return response()->noContent();
    }

    public function cambiarLetra(Request $request, string $lado)
    {
        $lado = Entrada::lado($lado);
        $letra = Entrada::letra($request->input('letra'));

        return response()->json(['letras' => $this->sala->cambiarLetra($lado, $letra)]);
    }

    public function exportar(Request $request, ExportarService $exportar)
    {
        $formato = $request->query('formato');
        if (! in_array($formato, ['pdf', 'xlsx'], true)) {
            throw new ApiError('Datos inválidos', 400);
        }
        $fecha = now('America/Guatemala')->format('Y-m-d');

        if ($formato === 'pdf') {
            return response($exportar->pdf(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"asistencia-$fecha.pdf\"",
            ]);
        }

        return response($exportar->excel(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"asistencia-$fecha.xlsx\"",
        ]);
    }

    /** Importa invitados desde un Excel o CSV (ver ImportarService). */
    public function importar(Request $request, ImportarService $importar)
    {
        $archivo = $request->file('archivo');
        if (! $archivo || ! $archivo->isValid()) {
            throw new ApiError('Selecciona un archivo Excel (.xlsx) o CSV.', 400);
        }
        if ($archivo->getSize() > 5 * 1024 * 1024) {
            throw new ApiError('El archivo es demasiado grande (máximo 5 MB).', 400);
        }
        $reemplazar = filter_var($request->input('reemplazar'), FILTER_VALIDATE_BOOLEAN);

        return response()->json($importar->importar($importar->leer($archivo), $reemplazar));
    }

    public function plantilla(ImportarService $importar)
    {
        return response($importar->plantilla(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla-invitados.xlsx"',
        ]);
    }

    /** Todas las firmas registradas: 'carpeta' (ZIP con una imagen por firma) o 'pdf'. */
    public function exportarFirmas(Request $request, ExportarService $exportar)
    {
        $formato = $request->query('formato');
        if (! in_array($formato, ['carpeta', 'pdf'], true)) {
            throw new ApiError('Datos inválidos', 400);
        }
        $fecha = now('America/Guatemala')->format('Y-m-d');

        if ($formato === 'pdf') {
            return response($exportar->firmasPdf(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"firmas-$fecha.pdf\"",
            ]);
        }

        return response()->download($exportar->firmasZip(), "firmas-$fecha.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
