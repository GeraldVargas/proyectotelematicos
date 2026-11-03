<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Radiotaxi extends Model
{
    protected $table = 'radiotaxis';

    protected $guarded = ['id', 'geom'];

    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'posicion_actualizada_en' => 'datetime',
        ];
    }

    public function scopeConGeoJson(Builder $query): Builder
    {
        return $query->select(['radiotaxis.*'])
            ->selectRaw('ST_AsGeoJSON(radiotaxis.geom, 7) AS geometry_json');
    }

    public function moverA(float $lng, float $lat): void
    {
        DB::update(
            'UPDATE radiotaxis SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), posicion_actualizada_en = ?, updated_at = ? WHERE id = ?',
            [$lng, $lat, now(), now(), $this->id]
        );
    }
}
