<?php

namespace App\Support;

/**
 * Construye respuestas GeoJSON (RFC 7946). La geometría llega ya serializada por PostGIS.
 */
class GeoJson
{
    public static function feature(int $id, string $geometryJson, array $properties): array
    {
        return [
            'type' => 'Feature',
            'id' => $id,
            'geometry' => json_decode($geometryJson, true),
            'properties' => (object) $properties,
        ];
    }

    public static function collection(iterable $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => is_array($features) ? array_values($features) : iterator_to_array($features, false),
        ];
    }
}
