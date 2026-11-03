<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiotaxis', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sindicato')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->boolean('disponible')->default(true);
            // demo = datos de demostración | monolito = parada base migrada del sistema actual
            $table->string('origen', 20)->default('demo');
            $table->unsignedBigInteger('monolito_id')->nullable()->unique();
            $table->geometry('geom', subtype: 'point', srid: 4326)->nullable();
            $table->timestamp('posicion_actualizada_en')->nullable();
            $table->timestamps();

            $table->spatialIndex('geom'); // GiST
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiotaxis');
    }
};
