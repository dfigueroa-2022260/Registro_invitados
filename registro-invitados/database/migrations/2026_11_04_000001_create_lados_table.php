<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lados', function (Blueprint $table) {
            $table->string('lado', 20)->primary();
            $table->string('letra', 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lados');
    }
};
