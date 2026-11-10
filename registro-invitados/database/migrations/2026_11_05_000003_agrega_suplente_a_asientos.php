<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asientos', function (Blueprint $table) {
            // Invitado suplente: ocupa el mismo asiento si el titular no llega (misma empresa que el titular).
            $table->string('suplente_nombres', 120)->default('');
            $table->string('suplente_documento', 40)->default('');
            // Quién firmó: 'titular' o 'suplente' (null = firmas anteriores, que son del titular).
            $table->string('firma_por', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('asientos', function (Blueprint $table) {
            $table->dropColumn(['suplente_nombres', 'suplente_documento', 'firma_por']);
        });
    }
};
