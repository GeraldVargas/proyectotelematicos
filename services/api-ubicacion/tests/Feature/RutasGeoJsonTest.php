<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RutasGeoJsonTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_rutas_devuelve_feature_collection_valida(): void
    {
        $res = $this->getJson('/api/ubicacion/rutas');

        $res->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json')
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonStructure(['features' => [['type', 'id', 'geometry' => ['type', 'coordinates'], 'properties' => ['linea', 'sindicato', 'color']]]]);

        $features = $res->json('features');
        $this->assertGreaterThanOrEqual(3, count($features));

        foreach ($features as $f) {
            $this->assertSame('Feature', $f['type']);
            $this->assertSame('LineString', $f['geometry']['type']);
            $this->assertGreaterThan(1, count($f['geometry']['coordinates']));
            $this->assertCoordenadasEnCochabamba($f['geometry']['coordinates']);
        }
    }

    public function test_filtro_por_linea(): void
    {
        $this->getJson('/api/ubicacion/rutas?linea=252')
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.linea', '252');
    }

    public function test_ruta_por_id_incluye_paradas_ordenadas(): void
    {
        $id = $this->getJson('/api/ubicacion/rutas?linea=201')->json('features.0.id');

        $res = $this->getJson("/api/ubicacion/rutas/{$id}");

        $res->assertOk()
            ->assertJsonPath('type', 'Feature')
            ->assertJsonPath('geometry.type', 'LineString')
            ->assertJsonPath('properties.linea', '201');

        $ordenes = array_column($res->json('properties.paradas'), 'orden');
        $this->assertNotEmpty($ordenes);
        $this->assertSame(range(1, count($ordenes)), $ordenes);
    }

    public function test_ruta_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/ubicacion/rutas/999999')->assertNotFound();
    }

    public function test_paradas_son_puntos_lng_lat(): void
    {
        $res = $this->getJson('/api/ubicacion/paradas');

        $res->assertOk()->assertJsonPath('type', 'FeatureCollection');

        foreach ($res->json('features') as $f) {
            $this->assertSame('Point', $f['geometry']['type']);
            $this->assertCoordenadasEnCochabamba([$f['geometry']['coordinates']]);
            $this->assertNotEmpty($f['properties']['rutas']);
        }
    }

    public function test_paradas_filtradas_por_ruta(): void
    {
        $id = $this->getJson('/api/ubicacion/rutas?linea=290')->json('features.0.id');

        $features = $this->getJson("/api/ubicacion/paradas?ruta_id={$id}")->assertOk()->json('features');

        $this->assertNotEmpty($features);
        foreach ($features as $f) {
            $this->assertContains($id, array_column($f['properties']['rutas'], 'id'));
        }
    }

    /** Detecta el error clásico de invertir lat/lng: Cochabamba está en lng≈-66, lat≈-17. */
    private function assertCoordenadasEnCochabamba(array $coordenadas): void
    {
        foreach ($coordenadas as [$lng, $lat]) {
            $this->assertTrue($lng > -66.5 && $lng < -65.9, "Longitud fuera de Cochabamba: {$lng}");
            $this->assertTrue($lat > -17.6 && $lat < -17.2, "Latitud fuera de Cochabamba: {$lat}");
        }
    }
}
