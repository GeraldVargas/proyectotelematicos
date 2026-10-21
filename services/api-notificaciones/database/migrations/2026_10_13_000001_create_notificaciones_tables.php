<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id es el claim sub del JWT; no hay FK porque los usuarios viven en otra BD
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('linea', 20);
            $table->timestamps();

            $table->unique(['user_id', 'linea']);
            $table->index('linea');
        });

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('tipo', 60);
            $table->string('titulo');
            $table->text('mensaje');
            $table->json('datos')->nullable();
            $table->uuid('evento_id')->nullable();
            $table->timestamp('leida_en')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'evento_id']);
        });

        // Registro de eventos consumidos: hace el consumo idempotente y sirve de auditoría
        Schema::create('eventos_recibidos', function (Blueprint $table) {
            $table->uuid('evento_id')->primary();
            $table->string('tipo', 60);
            $table->string('origen', 60);
            $table->json('payload');
            $table->unsignedInteger('notificaciones_creadas')->default(0);
            $table->timestamp('recibido_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_recibidos');
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('suscripciones');
    }
};
