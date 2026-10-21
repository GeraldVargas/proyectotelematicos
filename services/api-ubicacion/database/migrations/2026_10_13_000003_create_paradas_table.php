<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paradas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('osm_node_id')->nullable()->unique();
            $table->geometry('geom', subtype: 'point', srid: 4326)->nullable();
            $table->timestamps();

            $table->spatialIndex('geom'); // GiST
        });

        // Una parada puede ser compartida por varias líneas
        Schema::create('ruta_parada', function (Blueprint $table) {
            $table->foreignId('ruta_id')->constrained('rutas')->cascadeOnDelete();
            $table->foreignId('parada_id')->constrained('paradas')->cascadeOnDelete();
            $table->unsignedInteger('orden');

            $table->primary(['ruta_id', 'parada_id']);
            $table->unique(['ruta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_parada');
        Schema::dropIfExists('paradas');
    }
};
