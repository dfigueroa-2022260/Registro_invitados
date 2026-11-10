<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\Asiento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Importa invitados desde Excel/CSV. Columnas (por el nombre del encabezado, en cualquier orden):
 * Asiento (opcional, ej. A-5) · Nombres y apellidos · ID · Empresa o asociación · Categoría ·
 * Suplente (nombres) · ID del suplente.
 */
class ImportarService
{
    private const MAX_FILAS = 2000;
    private const MAX_ERRORES_MOSTRADOS = 60;

    public function __construct(private SalaService $sala)
    {
    }

    // ------------------------------------------------------------------ lectura

    /** Lee el archivo y devuelve las filas como arreglos de columnas ('asiento', 'nombres', ...) con su número de fila. */
    public function leer(UploadedFile $archivo): array
    {
        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        $ruta = $archivo->getRealPath();

        try {
            if (in_array($ext, ['xlsx', 'xlsm'], true)) {
                $filas = $this->leerHoja('Xlsx', $ruta);
            } elseif ($ext === 'xls') {
                $filas = $this->leerHoja('Xls', $ruta);
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $filas = $this->leerCsv($ruta);
            } else {
                throw new ApiError('Usa un archivo Excel (.xlsx) o CSV (.csv).', 400);
            }
        } catch (ApiError $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ApiError('No se pudo leer el archivo. Revisa que sea un Excel (.xlsx) o un CSV válido.', 400);
        }

        return $this->aRegistros($filas);
    }

    private function leerHoja(string $tipo, string $ruta): array
    {
        $lector = IOFactory::createReader($tipo);
        $lector->setReadDataOnly(true);

        return $lector->load($ruta)->getSheet(0)->toArray(null, true, false, false);
    }

    private function leerCsv(string $ruta): array
    {
        $contenido = (string) file_get_contents($ruta);
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        } elseif (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252'); // CSV de Excel en español
        }

        // El separador es el más frecuente en la primera línea (Excel en español usa ';').
        $primera = strtok($contenido, "\n") ?: '';
        $separador = ',';
        $mejor = -1;
        foreach ([',', ';', "\t"] as $candidato) {
            $veces = substr_count($primera, $candidato);
            if ($veces > $mejor) {
                [$mejor, $separador] = [$veces, $candidato];
            }
        }

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $contenido);
        rewind($flujo);
        $filas = [];
        while (($fila = fgetcsv($flujo, 0, $separador, '"', '\\')) !== false) {
            $filas[] = $fila;
        }
        fclose($flujo);

        return $filas;
    }

    /** Detecta la fila de encabezados y convierte cada fila en ['fila' => n, 'asiento' => ..., 'nombres' => ...]. */
    private function aRegistros(array $filas): array
    {
        $encabezado = null;
        $mapa = [];
        foreach (array_slice($filas, 0, 6, true) as $i => $fila) {
            $candidato = [];
            foreach ($fila as $col => $texto) {
                $campo = $this->campoDeEncabezado($this->celda($texto));
                if ($campo && ! in_array($campo, $candidato, true)) {
                    $candidato[$col] = $campo;
                }
            }
            if (in_array('nombres', $candidato, true)) {
                [$encabezado, $mapa] = [$i, $candidato];
                break;
            }
        }
        if ($encabezado === null) {
            throw new ApiError('No encontré la columna «Nombres y apellidos». La primera fila debe tener los encabezados (descarga la plantilla).', 400);
        }

        $registros = [];
        foreach ($filas as $i => $fila) {
            if ($i <= $encabezado) {
                continue;
            }
            $registro = ['fila' => $i + 1];
            $vacia = true;
            foreach ($mapa as $col => $campo) {
                $valor = $this->celda($fila[$col] ?? null);
                if (in_array($valor, ['—', '-', '–'], true)) {
                    $valor = ''; // las exportaciones usan — para lo vacío
                }
                $registro[$campo] = $valor;
                $vacia = $vacia && $valor === '';
            }
            if (! $vacia) {
                $registros[] = $registro;
            }
        }

        if (! $registros) {
            throw new ApiError('El archivo no tiene invitados para importar.', 400);
        }
        if (count($registros) > self::MAX_FILAS) {
            throw new ApiError('El archivo tiene demasiadas filas (máximo '.self::MAX_FILAS.').', 400);
        }

        return $registros;
    }

    private function campoDeEncabezado(string $texto): ?string
    {
        $h = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($texto))));
        if ($h === '') {
            return null;
        }
        $esId = (bool) preg_match('/\b(id|dpi|cui|documento)\b/', $h);

        if (str_contains($h, 'suplente')) {
            return $esId ? 'suplente_documento' : 'suplente_nombres';
        }
        if (str_contains($h, 'asiento') || in_array($h, ['n o', 'no', 'numero', 'n'], true)) {
            return 'asiento';
        }
        if (str_contains($h, 'empresa') || str_contains($h, 'asociacion')) {
            return 'empresa';
        }
        if (str_contains($h, 'nombre')) {
            return 'nombres';
        }
        if ($esId) {
            return 'documento';
        }
        if (str_contains($h, 'categoria')) {
            return 'categoria';
        }

        return null;
    }

    private function celda(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_float($valor) && floor($valor) == $valor && abs($valor) < 1e15) {
            $valor = sprintf('%.0f', $valor); // un ID numérico no debe quedar como 1.2E+9
        }

        return trim((string) preg_replace('/\s+/u', ' ', (string) $valor));
    }

    // ------------------------------------------------------------------ importación

    /**
     * Aplica las filas a la sala. No borra a nadie: asigna, actualiza o (con $reemplazar) cambia al titular de un asiento.
     * Devuelve el resumen con los errores por fila.
     */
    public function importar(array $registros, bool $reemplazar): array
    {
        $letras = $this->sala->letras();
        $asientos = $this->sala->ordenados($letras);

        $porEtiqueta = [];
        $libres = [];
        $personas = []; // clave persona => id de asiento
        $porId = [];
        foreach ($asientos as $a) {
            $porId[$a->id] = $a;
            $porEtiqueta[$a->lado.'|'.$a->numero] = $a;
            if ((string) $a->nombres === '') {
                $libres[$a->id] = $a;
            } else {
                $personas[$this->clavePersona($a->nombres, (string) $a->empresa)] = $a->id;
            }
        }

        $resumen = ['agregados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => [], 'totalErrores' => 0];
        $tocados = []; // id de asiento => fila que lo tocó
        $error = function (int $fila, string $texto) use (&$resumen) {
            $resumen['omitidos']++;
            $resumen['totalErrores']++;
            if (count($resumen['errores']) < self::MAX_ERRORES_MOSTRADOS) {
                $resumen['errores'][] = "Fila $fila: $texto";
            }
        };

        DB::transaction(function () use ($registros, $reemplazar, $letras, &$porEtiqueta, &$libres, &$personas, &$tocados, &$resumen, $error) {
            foreach ($registros as $r) {
                $fila = $r['fila'];
                $nombres = $r['nombres'] ?? '';
                $empresa = $r['empresa'] ?? '';
                $documento = $r['documento'] ?? '';
                $categoria = $r['categoria'] ?? '';
                $suplente = $r['suplente_nombres'] ?? '';
                $suplenteDoc = $r['suplente_documento'] ?? '';

                if ($nombres === '') {
                    $error($fila, 'falta el nombre del invitado.');
                    continue;
                }
                if ($empresa === '') {
                    $error($fila, "falta la empresa o asociación de $nombres (sin ella no podría registrarse).");
                    continue;
                }
                foreach ([['Nombres', $nombres, 120], ['Empresa', $empresa, 120], ['ID', $documento, 40], ['Categoría', $categoria, 60], ['Suplente', $suplente, 120], ['ID del suplente', $suplenteDoc, 40]] as [$campo, $valor, $max]) {
                    if (mb_strlen($valor) > $max) {
                        $error($fila, "«$campo» no puede superar $max caracteres.");
                        continue 2;
                    }
                }
                if ($suplente === '' && $suplenteDoc !== '') {
                    $suplenteDoc = '';
                }

                // ----- asiento de destino
                $destino = null;
                if (($r['asiento'] ?? '') !== '') {
                    $destino = $this->resolverAsiento($r['asiento'], $letras, $porEtiqueta);
                    if ($destino === false) {
                        $error($fila, "«{$r['asiento']}» no es un asiento válido (usa el formato A-5).");
                        continue;
                    }
                    if ($destino === null) {
                        $error($fila, "el asiento {$r['asiento']} no existe. Agrégalo antes desde el mapa (botón + del admin).");
                        continue;
                    }
                } else {
                    $destino = reset($libres) ?: null;
                    if (! $destino) {
                        $error($fila, "no quedan asientos libres para $nombres. Agrega asientos desde el mapa y vuelve a importar.");
                        continue;
                    }
                }

                $etiqueta = $this->sala->etiqueta($destino, $letras);
                if (isset($tocados[$destino->id])) {
                    $error($fila, "el asiento $etiqueta ya se asignó en la fila {$tocados[$destino->id]}.");
                    continue;
                }

                // ----- la misma persona no puede estar en dos asientos
                $clave = $this->clavePersona($nombres, $empresa);
                if (isset($personas[$clave]) && $personas[$clave] !== $destino->id) {
                    $error($fila, "$nombres ($empresa) ya tiene otro asiento asignado.");
                    continue;
                }

                $ocupado = (string) $destino->nombres !== '';
                $mismaPersona = $ocupado && $this->clavePersona($destino->nombres, (string) $destino->empresa) === $clave;

                if ($ocupado && ! $mismaPersona && ! $reemplazar) {
                    $error($fila, "el asiento $etiqueta ya está asignado a {$destino->nombres}. Marca «Reemplazar» si quieres cambiarlo.");
                    continue;
                }

                if ($mismaPersona) {
                    // Misma persona: se completan sus datos; una celda vacía no borra lo que ya había. Su firma se conserva.
                    $destino->fill([
                        'nombres' => $nombres,
                        'empresa' => $empresa,
                        'documento' => $documento !== '' ? $documento : $destino->documento,
                        'categoria' => $categoria !== '' ? $categoria : $destino->categoria,
                        'suplente_nombres' => $suplente !== '' ? $suplente : $destino->suplente_nombres,
                        'suplente_documento' => $suplente !== '' ? $suplenteDoc : $destino->suplente_documento,
                    ]);
                    if ($suplente !== '' && $this->sala->firmante($destino) === SalaService::SUPLENTE && $destino->firmado
                        && $this->sala->normalizar($suplente) !== $this->sala->normalizar((string) $destino->getOriginal('suplente_nombres'))) {
                        $this->sala->limpiarFirma($destino); // cambió el suplente que había firmado
                    }
                    $resumen['actualizados']++;
                } else {
                    if ($ocupado) {
                        // Otro titular: su firma y su registro dejan de aplicar.
                        $this->sala->limpiarFirma($destino);
                        unset($personas[$this->clavePersona($destino->nombres, (string) $destino->empresa)]);
                        $resumen['actualizados']++;
                    } else {
                        $resumen['agregados']++;
                    }
                    $destino->fill([
                        'nombres' => $nombres,
                        'empresa' => $empresa,
                        'documento' => $documento,
                        'categoria' => $categoria,
                        'suplente_nombres' => $suplente,
                        'suplente_documento' => $suplenteDoc,
                    ]);
                }

                $destino->save();
                unset($libres[$destino->id]);
                $personas[$clave] = $destino->id;
                $tocados[$destino->id] = $fila;
            }
        });

        return $resumen;
    }

    private function clavePersona(string $nombres, string $empresa): string
    {
        return $this->sala->normalizar($nombres).'|'.$this->sala->normalizar($empresa);
    }

    /** Devuelve el Asiento, null si no existe o false si el texto no tiene formato de asiento. */
    private function resolverAsiento(string $texto, array $letras, array $porEtiqueta): Asiento|null|false
    {
        if (! preg_match('/^(.+?)[\s\-_.]*(\d+)$/u', trim($texto), $m)) {
            return false;
        }
        $letra = mb_strtoupper(trim($m[1]));
        $numero = (int) $m[2];
        foreach ($letras as $lado => $l) {
            if (mb_strtoupper($l) === $letra) {
                return $porEtiqueta[$lado.'|'.$numero] ?? null;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ plantilla

    public function plantilla(): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Invitados');

        $columnas = [
            'A' => ['Asiento', 12], 'B' => ['Nombres y apellidos', 34], 'C' => ['ID', 18],
            'D' => ['Empresa o asociación', 34], 'E' => ['Categoría', 18],
            'F' => ['Suplente (nombres y apellidos)', 34], 'G' => ['ID del suplente', 18],
        ];
        foreach ($columnas as $letra => [$titulo, $ancho]) {
            $hoja->setCellValueExplicit($letra.'1', $titulo, DataType::TYPE_STRING);
            $hoja->getColumnDimension($letra)->setWidth($ancho);
        }
        $estilo = $hoja->getStyle('A1:G1');
        $estilo->getFont()->setBold(true);
        $estilo->getFont()->getColor()->setARGB('FFFFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID);
        $estilo->getFill()->getStartColor()->setARGB('FF2E4A8B');
        $hoja->freezePane('A2');
        // Columnas de texto: así un ID como 00123 no pierde sus ceros.
        $hoja->getStyle('A2:G500')->getNumberFormat()->setFormatCode('@');

        $info = $libro->createSheet();
        $info->setTitle('Instrucciones');
        $info->getColumnDimension('A')->setWidth(110);
        $lineas = [
            'Cómo importar invitados',
            '',
            '1. Llena la hoja «Invitados»: una fila por invitado, sin cambiar los encabezados de la fila 1.',
            '2. Asiento (opcional): por ejemplo A-5. Si lo dejas vacío, se asigna el siguiente asiento libre.',
            '3. Nombres y apellidos y Empresa o asociación son obligatorios (el invitado se registra con ellos).',
            '4. ID, Categoría, Suplente e ID del suplente son opcionales. El suplente ocupa el mismo asiento y es de la misma empresa.',
            '5. Los asientos deben existir en el mapa. La importación no borra invitados.',
            '6. Si un asiento ya tiene a otra persona, esa fila se omite, salvo que marques «Reemplazar» al importar.',
            '',
            'Ejemplo:  A-5 | Ana López Pérez | 2456789010101 | Asociación Nuevo Amanecer | Socio | Luis López | 2456789020202',
        ];
        foreach ($lineas as $i => $linea) {
            $info->setCellValueExplicit('A'.($i + 1), $linea, DataType::TYPE_STRING);
        }
        $info->getStyle('A1')->getFont()->setBold(true);
        $libro->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'plantilla');
        (new Xlsx($libro))->save($tmp);
        $contenido = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $contenido;
    }
}
