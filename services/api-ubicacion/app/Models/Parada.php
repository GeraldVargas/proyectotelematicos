<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Parada extends Model
{
    protected $guarded = ['id', 'geom'];

    protected $hidden = ['geom', 'pivot'];

    public function rutas(): BelongsToMany
    {
        return $this->belongsToMany(Ruta::class, 'ruta_parada')->withPivot('orden');
    }

    public function scopeConGeoJson(Builder $query): Builder
    {
        return $query->select(['paradas.id', 'paradas.nombre', 'paradas.descripcion'])
            ->selectRaw('ST_AsGeoJSON(paradas.geom, 7) AS geometry_json');
    }
}
