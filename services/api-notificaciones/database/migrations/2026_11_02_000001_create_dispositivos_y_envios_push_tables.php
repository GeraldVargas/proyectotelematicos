<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un token FCM identifica una instalación de la app: pertenece a un solo usuario a la vez
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('token', 512)->unique();
            $table->string('plataforma', 20)->default('android');
            $table->timestamps();
        });

        // Bandeja de salida de push: se escribe junto con la notificación y el worker la reintenta
        Schema::create('envios_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notificacion_id')->constrained('notificaciones')->cascadeOnDelete();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente'); // pendiente | enviado | fallido
            $table->unsignedInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamp('proximo_intento_en')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();

            $table->unique(['notificacion_id', 'dispositivo_id']);
            $table->index(['estado', 'proximo_intento_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_push');
        Schema::dropIfExists('dispositivos');
    }
};
