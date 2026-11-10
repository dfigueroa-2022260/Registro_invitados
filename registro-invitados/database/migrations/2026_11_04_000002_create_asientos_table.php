<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos', function (Blueprint $table) {
            $table->id(); // los ids no se reutilizan: un permiso viejo nunca apunta a otra persona
            $table->string('lado', 20)->index();
            $table->unsignedInteger('numero');
            $table->string('nombres', 120)->default('');
            $table->string('documento', 40)->default('');
            $table->string('empresa', 120)->default('');
            $table->string('categoria', 60)->default('');
            $table->boolean('firmado')->default(false);
            $table->timestamp('fecha_firma')->nullable();
            $table->string('firma_archivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos');
    }
};
