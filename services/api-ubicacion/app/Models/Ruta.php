<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ruta extends Model
{
    public const ATRIBUTOS = [
        'id', 'linea', 'nombre', 'sindicato', 'tipo', 'desde', 'hasta',
        'tarifa', 'frecuencia_min', 'horario', 'color', 'estado',
    ];

    protected $guarded = ['id', 'geom'];

    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return [
            'tarifa' => 'float',
            'frecuencia_min' => 'integer',
        ];
    }

    public function paradas(): BelongsToMany
    {
        return $this->belongsToMany(Parada::class, 'ruta_parada')
            ->withPivot('orden')
            ->orderByPivot('orden');
    }

    /** Agrega la geometría como texto GeoJSON en la columna virtual geometry_json. */
    public function scopeConGeoJson(Builder $query): Builder
    {
        return $query->select(array_map(fn ($c) => "rutas.$c", self::ATRIBUTOS))
            ->selectRaw('ST_AsGeoJSON(rutas.geom, 7) AS geometry_json');
    }
}
