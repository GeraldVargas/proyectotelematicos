<?php

namespace Tests\Feature;

use App\Models\Parada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParadasCercanasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Otras clases de prueba siembran paradas reales en la BD compartida
        Parada::query()->delete();
    }

    private function parada(float $lng, float $lat): int
    {
        $p = Parada::create(['nombre' => 'Parada de prueba']);
        DB::update('UPDATE paradas SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?', [$lng, $lat, $p->id]);

        return $p->id;
    }

    public function test_busqueda_en_metros_filtra_y_ordena_por_distancia(): void
    {
        $cercana = $this->parada(-66.2405, -17.3895);
        $segunda = $this->parada(-66.2405, -17.3905);
        $this->parada(-66.2405, -17.4895);
        $res = $this->getJson('/api/ubicacion/paradas/cercanas?lat=-17.3895&lng=-66.2405&radio=300');
        $res->assertOk()->assertHeader('Content-Type', 'application/geo+json')->assertJsonCount(2, 'features')
            ->assertJsonPath('features.0.id', $cercana)->assertJsonPath('features.1.id', $segunda);
        $this->assertEquals(0, $res->json('features.0.properties.distancia_m'));
        $this->assertGreaterThan(100, $res->json('features.1.properties.distancia_m'));
        $this->assertLessThan(120, $res->json('features.1.properties.distancia_m'));
    }

    public function test_cercania_vacia_y_limite_de_resultados(): void
    {
        $this->getJson('/api/ubicacion/paradas/cercanas?lat=0&lng=0')->assertOk()->assertJsonCount(0, 'features');
        $this->parada(-66.2405, -17.3895);
        $this->parada(-66.2405, -17.3905);
        $this->getJson('/api/ubicacion/paradas/cercanas?lat=-17.3895&lng=-66.2405&limite=1')
            ->assertOk()->assertJsonCount(1, 'features');
    }

    public function test_parametros_invalidos_devuelven_422(): void
    {
        foreach (['', '?lat=91&lng=0', '?lat=0&lng=181', '?lat=0&lng=0&radio=0', '?lat=0&lng=0&radio=5001', '?lat=0&lng=0&limite=101'] as $query) {
            $this->getJson('/api/ubicacion/paradas/cercanas'.$query)->assertUnprocessable();
        }
    }
}
