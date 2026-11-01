<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_salida', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('payload');
            $table->timestampTz('publicado_en')->nullable()->index();
            $table->unsignedInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestampsTz();
        });
        // El índice de geometry no cubre una búsqueda en metros sobre geography.
        DB::statement('CREATE INDEX paradas_geography_gist ON paradas USING GIST ((geom::geography))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS paradas_geography_gist');
        Schema::dropIfExists('eventos_salida');
    }
};
