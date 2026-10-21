<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            $table->string('linea', 20);
            $table->string('nombre');
            $table->string('sindicato')->nullable();
            $table->string('tipo', 30)->nullable(); // Trufi, MiniBus, MicroBus
            $table->string('desde')->nullable();
            $table->string('hasta')->nullable();
            $table->decimal('tarifa', 6, 2)->nullable();
            $table->unsignedInteger('frecuencia_min')->nullable();
            $table->string('horario')->nullable();
            $table->char('color', 7)->default('#1E88E5');
            $table->string('estado', 20)->default('activa'); // activa | suspendida
            // Origen del dato, para poder re-sincronizar sin duplicar
            $table->unsignedBigInteger('monolito_idtrufi')->nullable()->unique();
            $table->unsignedBigInteger('osm_relation_id')->nullable()->unique();
            // WGS 84; GeoJSON siempre en orden [lng, lat]. Nullable solo porque CargadorRutas
            // inserta la fila y asigna la geometría con ST_GeomFromGeoJSON en la misma transacción.
            $table->geometry('geom', subtype: 'linestring', srid: 4326)->nullable();
            $table->timestamps();

            $table->spatialIndex('geom'); // GiST
            $table->index('linea');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
