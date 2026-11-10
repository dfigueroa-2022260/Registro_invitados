<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las firmas pasan de archivos PNG en una carpeta a la base de datos (Base64).
 * Las firmas que ya estaban guardadas como archivo se copian a la tabla; los archivos viejos NO se borran
 * (puedes eliminar la carpeta storage/app/firmas cuando verifiques que todo se ve bien).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asiento_id')->unique();
            $table->longText('imagen'); // PNG en Base64, sin el prefijo "data:image/png;base64,"
            $table->foreign('asiento_id')->references('id')->on('asientos')->cascadeOnDelete();
        });

        $carpeta = rtrim((string) config('filesystems.disks.firmas.root'), '/\\');
        $antiguas = DB::table('asientos')->where('firmado', true)->whereNotNull('firma_archivo')->get(['id', 'firma_archivo']);
        foreach ($antiguas as $a) {
            $ruta = $carpeta.DIRECTORY_SEPARATOR.$a->firma_archivo;
            if (is_file($ruta)) {
                DB::table('firmas')->insert([
                    'asiento_id' => $a->id,
                    'imagen' => base64_encode((string) file_get_contents($ruta)),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('firmas');
    }
};
