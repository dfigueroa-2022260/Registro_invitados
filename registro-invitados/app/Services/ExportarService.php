<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\Firma;
use Carbon\CarbonInterface;
use FPDF;
use Illuminate\Support\Str;
use ZipArchive;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Lista de asistencia en PDF y Excel, con la firma guardada de cada invitado. */
class ExportarService
{
    private const AZUL = '2E4A8B';

    /** Archivos temporales (miniaturas de firmas) que se borran al terminar. */
    private array $temporales = [];

    public function __construct(private SalaService $sala)
    {
    }

    private function fechaLocal(?CarbonInterface $fecha): string
    {
        return $fecha ? $fecha->copy()->setTimezone('America/Guatemala')->format('d/m/Y H:i') : '—';
    }

    /** Solo asientos con titular: un asiento eliminado o vaciado ya no aparece (ni su firma). */
    private function filas(): array
    {
        $letras = $this->sala->letras();

        return $this->sala->ordenados($letras)
            ->filter(fn ($a) => (string) $a->nombres !== '')
            ->map(function ($a) use ($letras) {
                $suplente = $this->sala->firmante($a) === SalaService::SUPLENTE;

                return [
                    'asiento' => $this->sala->etiqueta($a, $letras),
                    'nombres' => (string) $a->nombres,
                    'documento' => $a->documento ?: '—',
                    'empresa' => $a->empresa ?: '—',
                    'categoria' => $a->categoria ?: '—',
                    'suplente' => $a->suplente_nombres ?: '—',
                    'asistio' => (bool) $a->firmado,
                    'asistencia' => $a->firmado ? ($suplente ? 'Asistió (suplente)' : 'Asistió') : 'Pendiente',
                    'fecha' => $this->fechaLocal($a->fecha_firma),
                    'firma' => $a->firmado ? $this->miniaturaFirma(Firma::where('asiento_id', $a->id)->value('imagen')) : null,
                ];
            })
            ->values()
            ->all();
    }

    private function resumen(array $filas): array
    {
        return [count(array_filter($filas, fn ($f) => $f['asistio'])), count($filas)];
    }

    /**
     * Copia reducida de la firma (fondo blanco) en un archivo temporal, a partir del Base64 guardado en la base
     * de datos. Devuelve la ruta o null si no hay firma. Si GD no está disponible se usa el PNG tal cual.
     */
    private function miniaturaFirma(?string $base64, int $maxAncho = 360, int $maxAlto = 140): ?string
    {
        $binario = $base64 ? base64_decode($base64, true) : false;
        if ($binario === false || $binario === '') {
            return null;
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $origen = @imagecreatefromstring($binario);
            if ($origen !== false) {
                $w = imagesx($origen);
                $h = imagesy($origen);
                $escala = min(1, $maxAncho / max(1, $w), $maxAlto / max(1, $h));
                $nw = max(1, (int) round($w * $escala));
                $nh = max(1, (int) round($h * $escala));
                $destino = imagecreatetruecolor($nw, $nh);
                imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
                imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);

                $base = tempnam(sys_get_temp_dir(), 'firma');
                $ruta = $base.'.png';
                $guardada = @imagepng($destino, $ruta, 6);
                @unlink($base);
                if ($guardada) {
                    $this->temporales[] = $ruta;

                    return $ruta;
                }
            }
        }

        $base = tempnam(sys_get_temp_dir(), 'firma');
        $ruta = $base.'.png';
        file_put_contents($ruta, $binario);
        @unlink($base);
        $this->temporales[] = $ruta;

        return $ruta;
    }

    private function limpiarTemporales(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }
        $this->temporales = [];
    }

    /** Devuelve el contenido del .xlsx. */
    public function excel(): string
    {
        try {
            return $this->construirExcel();
        } finally {
            $this->limpiarTemporales();
        }
    }

    private function construirExcel(): string
    {
        $filas = $this->filas();
        [$asistieron, $total] = $this->resumen($filas);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Asistencia');

        $columnas = [
            'A' => ['Asiento', 12],
            'B' => ['Nombres y apellidos', 32],
            'C' => ['ID', 18],
            'D' => ['Empresa o asociación', 32],
            'E' => ['Categoría', 18],
            'F' => ['Suplente', 28],
            'G' => ['Asistencia', 20],
            'H' => ['Fecha y hora de firma', 22],
            'I' => ['Firma', 26],
        ];
        foreach ($columnas as $letra => [$titulo, $ancho]) {
            $hoja->setCellValueExplicit($letra.'1', $titulo, DataType::TYPE_STRING);
            $hoja->getColumnDimension($letra)->setWidth($ancho);
        }

        $encabezado = $hoja->getStyle('A1:I1');
        $encabezado->getFont()->setBold(true);
        $encabezado->getFont()->getColor()->setARGB('FFFFFFFF');
        $encabezado->getFill()->setFillType(Fill::FILL_SOLID);
        $encabezado->getFill()->getStartColor()->setARGB('FF'.self::AZUL);
        $encabezado->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->freezePane('A2');

        $fila = 2;
        foreach ($filas as $f) {
            $valores = ['A' => $f['asiento'], 'B' => $f['nombres'], 'C' => $f['documento'], 'D' => $f['empresa'],
                'E' => $f['categoria'], 'F' => $f['suplente'], 'G' => $f['asistencia'], 'H' => $f['fecha']];
            foreach ($valores as $letra => $valor) {
                // Siempre como texto: un ID como 00123 no debe convertirse en número.
                $hoja->setCellValueExplicit($letra.$fila, (string) $valor, DataType::TYPE_STRING);
            }
            $hoja->getRowDimension($fila)->setRowHeight(40);
            $estilo = $hoja->getStyle('G'.$fila)->getFont();
            $estilo->setBold(true);
            $estilo->getColor()->setARGB($f['asistio'] ? 'FF1B7F3B' : 'FF9A6700');

            if ($f['firma']) {
                $dibujo = new Drawing();
                $dibujo->setName('Firma '.$f['asiento']);
                $dibujo->setPath($f['firma']);
                $dibujo->setCoordinates('I'.$fila);
                $dibujo->setHeight(46);
                $dibujo->setOffsetX(6);
                $dibujo->setOffsetY(4);
                $dibujo->setWorksheet($hoja);
            }
            $fila++;
        }
        $ultima = max(2, $fila - 1);
        $hoja->getStyle("A2:H$ultima")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->setAutoFilter("A1:I$ultima");

        $fila++;
        $hoja->setCellValueExplicit('B'.$fila, "Asistieron $asistieron de $total invitados", DataType::TYPE_STRING);
        $hoja->getStyle('B'.$fila)->getFont()->setBold(true);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($libro))->save($tmp);
        $contenido = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $contenido;
    }

    /** Devuelve el contenido del .pdf. */
    public function pdf(): string
    {
        try {
            return $this->construirPdf();
        } finally {
            $this->limpiarTemporales();
        }
    }

    private function construirPdf(): string
    {
        $filas = $this->filas();
        [$asistieron, $total] = $this->resumen($filas);

        $txt = fn (string $s): string => mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        $pdf = new FPDF('L', 'pt', 'A4');
        $pdf->SetMargins(36, 36, 36);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $columnas = [
            ['Asiento', 38], ['Nombres y apellidos', 124], ['ID', 62], ['Empresa o asociación', 110],
            ['Categoría', 58], ['Suplente', 88], ['Asistencia', 78], ['Fecha y hora', 76], ['Firma', 136],
        ];
        $anchoTotal = array_sum(array_column($columnas, 1));
        $izquierda = 36;
        $altoEnc = 20;
        $altoFila = 34;
        $limiteInferior = $pdf->GetPageHeight() - 36;
        [$ar, $ag, $ab] = [hexdec(substr(self::AZUL, 0, 2)), hexdec(substr(self::AZUL, 2, 2)), hexdec(substr(self::AZUL, 4, 2))];

        $pdf->SetXY($izquierda, 36);
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor($ar, $ag, $ab);
        $pdf->Cell(0, 22, $txt('UDEVIPO · Lista de asistencia'), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(68, 68, 68);
        $generado = $this->fechaLocal(now());
        $pdf->Cell(0, 14, $txt("Generado: $generado   ·   Asistieron $asistieron de $total invitados"), 0, 1);

        // Texto recortado con "..." si no cabe, centrado verticalmente en la fila.
        $celda = function (string $texto, float $x, float $y, float $ancho, float $alto) use ($pdf, $txt) {
            $texto = $txt($texto);
            $max = $ancho - 8;
            if ($pdf->GetStringWidth($texto) > $max) {
                while ($texto !== '' && $pdf->GetStringWidth($texto.'...') > $max) {
                    $texto = substr($texto, 0, -1);
                }
                $texto .= '...';
            }
            $pdf->SetXY($x + 4, $y + ($alto - 9) / 2);
            $pdf->Cell($max, 9, $texto, 0, 0, 'L');
        };

        $encabezado = function (float $y) use ($pdf, $columnas, $anchoTotal, $izquierda, $altoEnc, $ar, $ag, $ab, $celda) {
            $pdf->SetFillColor($ar, $ag, $ab);
            $pdf->Rect($izquierda, $y, $anchoTotal, $altoEnc, 'F');
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetTextColor(255, 255, 255);
            $x = $izquierda;
            foreach ($columnas as [$titulo, $ancho]) {
                $celda($titulo, $x, $y, $ancho, $altoEnc);
                $x += $ancho;
            }
        };

        $y = 84.0;
        $encabezado($y);
        $y += $altoEnc;

        foreach ($filas as $i => $f) {
            if ($y + $altoFila > $limiteInferior) {
                $pdf->AddPage();
                $y = 36.0;
                $encabezado($y);
                $y += $altoEnc;
            }
            if ($i % 2 === 0) {
                $pdf->SetFillColor(241, 243, 248);
                $pdf->Rect($izquierda, $y, $anchoTotal, $altoFila, 'F');
            }

            $valores = [$f['asiento'], $f['nombres'], $f['documento'], $f['empresa'], $f['categoria'], $f['suplente'], $f['asistencia'], $f['fecha']];
            $x = $izquierda;
            foreach ($valores as $k => $valor) {
                if ($k === 6) {
                    $pdf->SetFont('Helvetica', 'B', 8);
                    $f['asistio'] ? $pdf->SetTextColor(27, 127, 59) : $pdf->SetTextColor(154, 103, 0);
                } else {
                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->SetTextColor(34, 34, 34);
                }
                $celda((string) $valor, $x, $y, $columnas[$k][1], $altoFila);
                $x += $columnas[$k][1];
            }

            // Firma guardada, ajustada a la celda sin deformarla.
            if ($f['firma'] && ($medidas = @getimagesize($f['firma']))) {
                $maxAncho = $columnas[8][1] - 12;
                $maxAlto = $altoFila - 6;
                $razon = $medidas[0] / max(1, $medidas[1]);
                $ancho = min($maxAncho, $maxAlto * $razon);
                $alto = $ancho / $razon;
                $pdf->Image($f['firma'], $x + 6, $y + ($altoFila - $alto) / 2, $ancho, $alto, 'PNG');
            }
            $y += $altoFila;
        }

        return $pdf->Output('S');
    }

    // ------------------------------------------------------------------ exportar firmas

    /** Asientos con firma guardada, en orden de mesa y número, con los datos para rotularla. */
    private function firmasRegistradas(): array
    {
        $letras = $this->sala->letras();
        $conFirma = array_flip(Firma::pluck('asiento_id')->all()); // solo ids: las imágenes se leen una a una
        $items = [];
        foreach ($this->sala->ordenados($letras) as $a) {
            if (! isset($conFirma[$a->id])) {
                continue;
            }
            $rol = $this->sala->firmante($a);
            $items[] = [
                'id' => $a->id,
                'etiqueta' => $this->sala->etiqueta($a, $letras),
                'persona' => $this->sala->persona($a, $rol) ?: (string) $a->nombres,
                'empresa' => (string) $a->empresa,
                'rol' => $rol,
                'fecha' => $this->fechaLocal($a->fecha_firma),
            ];
        }

        return $items;
    }

    /** ZIP con una carpeta «firmas» y un PNG por firma (A-5_ana-lopez.png). Devuelve la ruta del ZIP temporal. */
    public function firmasZip(): string
    {
        $items = $this->firmasRegistradas();
        if (! $items) {
            throw new ApiError('Todavía no hay firmas registradas.', 404);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'firmas');
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($ruta);
            throw new ApiError('No se pudo crear el archivo ZIP.', 500);
        }

        $usados = [];
        $agregadas = 0;
        foreach ($items as $it) {
            $png = base64_decode((string) Firma::where('asiento_id', $it['id'])->value('imagen'), true);
            if ($png === false || $png === '') {
                continue;
            }
            $nombre = preg_replace('/[^A-Za-z0-9-]+/', '', Str::ascii($it['etiqueta'])).'_'.Str::slug($it['persona']);
            $final = $nombre;
            for ($n = 2; isset($usados[$final]); $n++) {
                $final = $nombre.'-'.$n;
            }
            $usados[$final] = true;
            $zip->addFromString("firmas/$final.png", $png);
            $agregadas++;
        }
        $zip->close();

        if ($agregadas === 0) {
            @unlink($ruta);
            throw new ApiError('Todavía no hay firmas registradas.', 404);
        }

        return $ruta;
    }

    /** PDF con todas las firmas registradas (10 por página), cada una con su asiento, nombre y fecha. */
    public function firmasPdf(): string
    {
        try {
            return $this->construirFirmasPdf();
        } finally {
            $this->limpiarTemporales();
        }
    }

    private function recortar(FPDF $pdf, string $texto, float $max): string
    {
        $texto = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        if ($pdf->GetStringWidth($texto) > $max) {
            while ($texto !== '' && $pdf->GetStringWidth($texto.'...') > $max) {
                $texto = substr($texto, 0, -1);
            }
            $texto .= '...';
        }

        return $texto;
    }

    private function construirFirmasPdf(): string
    {
        $items = $this->firmasRegistradas();
        if (! $items) {
            throw new ApiError('Todavía no hay firmas registradas.', 404);
        }

        $txt = fn (string $s): string => mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        $pdf = new FPDF('P', 'pt', 'A4');
        $pdf->SetMargins(36, 36, 36);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $hueco = 14;
        $ancho = ($pdf->GetPageWidth() - 72 - $hueco) / 2;
        $alto = 124;
        [$ar, $ag, $ab] = [hexdec(substr(self::AZUL, 0, 2)), hexdec(substr(self::AZUL, 2, 2)), hexdec(substr(self::AZUL, 4, 2))];

        $pdf->SetXY(36, 36);
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor($ar, $ag, $ab);
        $pdf->Cell(0, 22, $txt('UDEVIPO · Firmas registradas'), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(68, 68, 68);
        $pdf->Cell(0, 14, $txt('Generado: '.$this->fechaLocal(now()).'   ·   '.count($items).' firmas'), 0, 1);

        $y = 84.0;
        foreach ($items as $i => $it) {
            $col = $i % 2;
            if ($col === 0 && $i > 0) {
                $y += $alto + 10;
            }
            if ($col === 0 && $y + $alto > $pdf->GetPageHeight() - 36) {
                $pdf->AddPage();
                $y = 36.0;
            }
            $x = 36 + $col * ($ancho + $hueco);

            $pdf->SetDrawColor(200, 204, 214);
            $pdf->SetLineWidth(0.8);
            $pdf->Rect($x, $y, $ancho, $alto, 'D');

            // Firma, ajustada al recuadro sin deformarla.
            $ruta = $this->miniaturaFirma(Firma::where('asiento_id', $it['id'])->value('imagen'), 700, 280);
            if ($ruta && ($medidas = @getimagesize($ruta))) {
                $razon = $medidas[0] / max(1, $medidas[1]);
                $w = min($ancho - 16, 68 * $razon);
                $h = $w / $razon;
                $pdf->Image($ruta, $x + ($ancho - $w) / 2, $y + 8 + (68 - $h) / 2, $w, $h, 'PNG');
            }
            $pdf->SetDrawColor(225, 228, 236);
            $pdf->Line($x + 8, $y + 82, $x + $ancho - 8, $y + 82);

            $texto = $ancho - 16;
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(34, 34, 34);
            $pdf->SetXY($x + 8, $y + 87);
            $pdf->Cell($texto, 11, $this->recortar($pdf, $it['etiqueta'].' · '.$it['persona'], $texto), 0, 0);

            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetXY($x + 8, $y + 99);
            $pdf->Cell($texto, 10, $this->recortar($pdf, $it['empresa'] ?: '—', $texto), 0, 0);
            $pdf->SetXY($x + 8, $y + 110);
            $quien = $it['rol'] === SalaService::SUPLENTE ? ' (suplente)' : '';
            $pdf->Cell($texto, 10, $this->recortar($pdf, 'Firmó el '.$it['fecha'].$quien, $texto), 0, 0);
        }

        return $pdf->Output('S');
    }
}
