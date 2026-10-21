<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = ['user_id', 'tipo', 'titulo', 'mensaje', 'datos', 'evento_id', 'leida_en'];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'leida_en' => 'datetime',
        ];
    }
}
