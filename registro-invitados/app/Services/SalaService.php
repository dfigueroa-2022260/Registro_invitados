<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\Asiento;
use App\Models\Firma;
use App\Models\Lado;
use App\Support\Distribucion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalaService
{
    public const TITULAR = 'titular';
    public const SUPLENTE = 'suplente';

    /** Letra de cada mesa: ['central' => 'A', 'superior' => 'B', ...]. */
    public function letras(): array
    {
        return array_merge(Distribucion::letrasPredeterminadas(), Lado::pluck('letra', 'lado')->all());
    }

    public function etiqueta(Asiento $a, array $letras): string
    {
        return ($letras[$a->lado] ?? '?').'-'.$a->numero;
    }

    public function ref(Asiento $a, array $letras): array
    {
        return [
            'lado' => $a->lado,
            'numero' => $a->numero,
            'etiqueta' => $this->etiqueta($a, $letras),
            'zona' => Distribucion::nombre($a->lado),
        ];
    }

    /** Nombre de la persona que ocupa el asiento según su rol (titular o suplente). */
    public function persona(Asiento $a, string $rol): string
    {
        return trim((string) ($rol === self::SUPLENTE ? $a->suplente_nombres : $a->nombres));
    }

    /** Quién firmó el asiento (las firmas anteriores al suplente son del titular). */
    public function firmante(Asiento $a): string
    {
        return $a->firma_por === self::SUPLENTE ? self::SUPLENTE : self::TITULAR;
    }

    public function vista(Asiento $a, array $letras): array
    {
        return [
            'id' => $a->id,
            'lado' => $a->lado,
            'numero' => $a->numero,
            'etiqueta' => $this->etiqueta($a, $letras),
            'nombres' => (string) $a->nombres,
            'documento' => (string) $a->documento,
            'empresa' => (string) $a->empresa,
            'categoria' => (string) $a->categoria,
            'suplenteNombres' => (string) $a->suplente_nombres,
            'suplenteDocumento' => (string) $a->suplente_documento,
            'firmado' => (bool) $a->firmado,
            'firmaPor' => $a->firmado ? $this->firmante($a) : null,
            'fechaFirma' => $a->fecha_firma?->toIso8601ZuluString(),
        ];
    }

    /** Asientos ordenados por letra de fila y número. */
    public function ordenados(?array $letras = null): Collection
    {
        $letras ??= $this->letras();
        $clave = fn (string $t) => Str::upper(Str::ascii($t));

        return Asiento::all()
            ->sort(fn ($x, $y) => (strcmp($clave($letras[$x->lado]), $clave($letras[$y->lado])) ?: ($x->numero <=> $y->numero)))
            ->values();
    }

    public function listarAdmin(): array
    {
        $letras = $this->letras();

        return [
            'letras' => $letras,
            'grupos' => Distribucion::paraMapa(),
            'asientos' => $this->ordenados($letras)->map(fn ($a) => $this->vista($a, $letras))->all(),
        ];
    }

    /** Empresas con al menos un invitado asignado (alimentan el desplegable del login). */
    public function empresasConInvitados(): array
    {
        $nombres = Asiento::where('nombres', '!=', '')
            ->where('empresa', '!=', '')
            ->pluck('empresa')
            ->unique()
            ->all();
        $clave = fn (string $t) => Str::lower(Str::ascii($t));
        usort($nombres, fn ($a, $b) => strcmp($clave($a), $clave($b)) ?: strcmp($a, $b));

        return array_values($nombres);
    }

    public function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Busca a quien se registra: primero entre los titulares y, si no está, entre los suplentes
     * (el suplente pertenece a la empresa del titular). Devuelve ['asiento' => Asiento, 'rol' => 'titular'|'suplente'].
     */
    public function buscarInvitado(string $nombres, string $empresa): ?array
    {
        $n = $this->normalizar($nombres);
        $e = $this->normalizar($empresa);

        $asientos = Asiento::where('nombres', '!=', '')->get()
            ->filter(fn ($a) => $this->normalizar((string) $a->empresa) === $e);

        $titular = $asientos->first(fn ($a) => $this->normalizar((string) $a->nombres) === $n);
        if ($titular) {
            return ['asiento' => $titular, 'rol' => self::TITULAR];
        }

        $suplente = $asientos->first(
            fn ($a) => (string) $a->suplente_nombres !== '' && $this->normalizar((string) $a->suplente_nombres) === $n
        );

        return $suplente ? ['asiento' => $suplente, 'rol' => self::SUPLENTE] : null;
    }

    /** Vuelve a poner un asiento de la sala (solo los números que existen en esa mesa según el plano). */
    public function agregar(string $lado, int $numero): array
    {
        if (! in_array($numero, Distribucion::numeros($lado), true)) {
            throw new ApiError("El asiento $numero no existe en esa mesa.", 400);
        }
        if (Asiento::where('lado', $lado)->where('numero', $numero)->exists()) {
            throw new ApiError('Ese asiento ya existe.', 409);
        }
        $asiento = Asiento::create(['lado' => $lado, 'numero' => $numero])->refresh();

        return $this->vista($asiento, $this->letras());
    }

    /**
     * Guarda los datos del asiento. Si se vacía a quien firmó (titular, o suplente si fue él),
     * su firma deja de tener dueño y se borra (archivo incluido).
     */
    public function editar(int $id, array $datos): array
    {
        $asiento = Asiento::find($id) ?? throw new ApiError('El asiento no existe', 404);

        if ($datos['nombres'] === '' && $datos['suplente_nombres'] !== '') {
            throw new ApiError('Primero escribe el titular del asiento: el suplente va con un titular.', 400);
        }
        if ($datos['suplente_nombres'] === '') {
            $datos['suplente_documento'] = '';
        }

        $asiento->fill($datos);

        if ($asiento->firmado) {
            $quedoSinFirmante = $this->firmante($asiento) === self::SUPLENTE
                ? $datos['suplente_nombres'] === ''
                : $datos['nombres'] === '';
            if ($quedoSinFirmante) {
                $this->limpiarFirma($asiento);
            }
        }

        $asiento->save();

        return $this->vista($asiento, $this->letras());
    }

    /**
     * Quita un asiento de la sala (libre, asignado o con firma) junto con su firma guardada en la base de datos.
     * Los números son los impresos en las mesas, así que los demás no se renumeran; el asiento
     * quita su silla del mapa y se puede volver a agregar desde el admin.
     */
    public function eliminar(int $id): void
    {
        $asiento = Asiento::find($id) ?? throw new ApiError('El asiento no existe', 404);

        DB::transaction(function () use ($asiento) {
            Firma::where('asiento_id', $asiento->id)->delete();
            $asiento->delete();
        });
    }

    public function cambiarLetra(string $lado, string $letra): array
    {
        foreach ($this->letras() as $otro => $actual) {
            if ($otro !== $lado && mb_strtoupper($actual) === $letra) {
                throw new ApiError("La letra $letra ya la usa otra fila", 409);
            }
        }
        Lado::updateOrCreate(['lado' => $lado], ['letra' => $letra]);

        return $this->letras();
    }

    /** Guarda la firma (PNG en Base64) en la base de datos y marca el asiento como firmado. */
    public function marcarFirmado(Asiento $asiento, string $firmaBase64, string $rol): void
    {
        DB::transaction(function () use ($asiento, $firmaBase64, $rol) {
            Firma::updateOrCreate(['asiento_id' => $asiento->id], ['imagen' => $firmaBase64]);
            $asiento->update(['firmado' => true, 'fecha_firma' => now(), 'firma_por' => $rol]);
        });
    }

    /** Quita la firma del asiento (la borra de la base de datos y limpia los campos). No guarda el modelo. */
    public function limpiarFirma(Asiento $asiento): void
    {
        Firma::where('asiento_id', $asiento->id)->delete();
        $asiento->firmado = false;
        $asiento->fecha_firma = null;
        $asiento->firma_por = null;
    }
}
