<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvioPush extends Model
{
    protected $table = 'envios_push';

    protected $fillable = ['notificacion_id', 'dispositivo_id', 'estado', 'intentos', 'ultimo_error', 'proximo_intento_en', 'enviado_en'];

    protected function casts(): array
    {
        return [
            'proximo_intento_en' => 'datetime',
            'enviado_en' => 'datetime',
        ];
    }

    public function notificacion(): BelongsTo
    {
        return $this->belongsTo(Notificacion::class);
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }
}
