<?php

namespace Tests\Feature;

use App\Models\Radiotaxi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RadiotaxisTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['X-User-Id' => '1', 'X-User-Rol' => 'admin'];

    protected function setUp(): void
    {
        parent::setUp();

        // Otras clases de prueba siembran la BD compartida al migrarla
        Radiotaxi::query()->delete();
    }

    private function radiotaxi(array $atributos = [], ?array $lngLat = [-66.2405, -17.3895]): Radiotaxi
    {
        $radiotaxi = Radiotaxi::create($atributos + ['nombre' => 'Radio Taxi Prueba', 'origen' => 'demo']);
        if ($lngLat) {
            $radiotaxi->moverA(...$lngLat);
        }

        return $radiotaxi;
    }

    public function test_devuelve_solo_disponibles_con_posicion_como_geojson(): void
    {
        $disponible = $this->radiotaxi(['telefono' => '4441234']);
        $this->radiotaxi(['nombre' => 'Ocupado', 'disponible' => false]);
        $this->radiotaxi(['nombre' => 'Sin posición'], null);

        $this->getJson('/api/ubicacion/radiotaxis')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json')
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.id', $disponible->id)
            ->assertJsonPath('features.0.geometry', ['type' => 'Point', 'coordinates' => [-66.2405, -17.3895]])
            ->assertJsonPath('features.0.properties.telefono', '4441234')
            ->assertJsonPath('features.0.properties.origen', 'demo');
    }

    public function test_sin_radiotaxis_devuelve_coleccion_vacia(): void
    {
        $this->getJson('/api/ubicacion/radiotaxis')->assertOk()->assertJsonPath('features', []);
    }

    public function test_admin_actualiza_posicion_y_disponibilidad(): void
    {
        $radiotaxi = $this->radiotaxi();
        $this->travel(1)->minutes();

        $this->putJson("/api/ubicacion/radiotaxis/{$radiotaxi->id}/posicion", ['lat' => -17.4, 'lng' => -66.25], self::ADMIN)
            ->assertOk()
            ->assertJsonPath('geometry.coordinates', [-66.25, -17.4])
            ->assertJsonPath('properties.posicion_actualizada_en', now()->startOfSecond()->toIso8601String());

        $this->putJson("/api/ubicacion/radiotaxis/{$radiotaxi->id}/posicion", ['lat' => -17.4, 'lng' => -66.25, 'disponible' => false], self::ADMIN)
            ->assertOk()->assertJsonPath('properties.disponible', false);

        $this->getJson('/api/ubicacion/radiotaxis')->assertJsonCount(0, 'features');
    }

    public function test_solo_admin_puede_actualizar_la_posicion(): void
    {
        $radiotaxi = $this->radiotaxi();
        $url = "/api/ubicacion/radiotaxis/{$radiotaxi->id}/posicion";
        $datos = ['lat' => -17.4, 'lng' => -66.25];

        $this->putJson($url, $datos)->assertUnauthorized();
        foreach (['usuario', 'encargado'] as $rol) {
            $this->putJson($url, $datos, ['X-User-Id' => '2', 'X-User-Rol' => $rol])->assertForbidden();
        }
        $this->getJson('/api/ubicacion/radiotaxis')->assertJsonPath('features.0.geometry.coordinates', [-66.2405, -17.3895]);
    }

    public function test_posicion_invalida_o_radiotaxi_inexistente(): void
    {
        $url = '/api/ubicacion/radiotaxis/'.$this->radiotaxi()->id.'/posicion';

        foreach ([['lat' => 91, 'lng' => 0], ['lat' => 0, 'lng' => 181], ['lat' => 0], ['lng' => 0], ['lat' => 'x', 'lng' => 0]] as $datos) {
            $this->putJson($url, $datos, self::ADMIN)->assertUnprocessable();
        }
        $this->putJson('/api/ubicacion/radiotaxis/999999/posicion', ['lat' => 0, 'lng' => 0], self::ADMIN)->assertNotFound();
    }

    public function test_seeder_crea_demostracion_una_sola_vez_y_marcada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Radiotaxi::count());
        $this->assertSame(4, Radiotaxi::where('origen', 'demo')->count());
    }

    public function test_simulador_mueve_solo_los_de_demostracion_sobre_las_rutas(): void
    {
        $this->seed(DatabaseSeeder::class);
        $real = $this->radiotaxi(['nombre' => 'Real', 'origen' => 'monolito'], [-66.2, -17.4]);
        $antes = $this->getJson('/api/ubicacion/radiotaxis')->json('features');

        $this->artisan('radiotaxis:simular --una-vez')->assertSuccessful();

        $despues = collect($this->getJson('/api/ubicacion/radiotaxis')->json('features'))->keyBy('id');
        foreach ($antes as $f) {
            if ($f['id'] === $real->id) {
                $this->assertSame($f['geometry'], $despues[$f['id']]['geometry']);
            } else {
                $this->assertNotSame($f['geometry'], $despues[$f['id']]['geometry']);
            }
        }
        $sobreRuta = Radiotaxi::where('origen', 'demo')->whereRaw('EXISTS (SELECT 1 FROM rutas WHERE ST_DWithin(rutas.geom::geography, radiotaxis.geom::geography, 1))')->count();
        $this->assertSame(4, $sobreRuta);
    }

    public function test_migracion_del_monolito_importa_paradas_y_retira_demostracion(): void
    {
        config(['database.connections.monolito' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $monolito = Schema::connection('monolito');
        $monolito->create('sindicato_radiotaxis', function ($t) {
            $t->id();
            $t->string('nombre_comercial');
            $t->string('telefono_base');
        });
        $monolito->create('sindicato_radiotaxi_paradas', function ($t) {
            $t->id();
            $t->unsignedBigInteger('sindicato_radiotaxi_id');
            $t->decimal('latitud', 10, 7);
            $t->decimal('longitud', 10, 7);
            $t->boolean('estado');
        });
        $db = DB::connection('monolito');
        $db->table('sindicato_radiotaxis')->insert([
            ['id' => 1, 'nombre_comercial' => 'Radio Móvil Colcapirhua', 'telefono_base' => '4260000'],
            ['id' => 2, 'nombre_comercial' => 'Sin parada', 'telefono_base' => '4260001'],
        ]);
        $db->table('sindicato_radiotaxi_paradas')->insert(['sindicato_radiotaxi_id' => 1, 'latitud' => -17.39, 'longitud' => -66.24, 'estado' => 1]);
        $demo = $this->radiotaxi();

        $this->artisan('ubicacion:migrar-radiotaxis --dry-run')->assertSuccessful();
        $this->assertSame(1, Radiotaxi::count());
        $this->assertModelExists($demo);

        $this->artisan('ubicacion:migrar-radiotaxis')->assertSuccessful();
        $this->artisan('ubicacion:migrar-radiotaxis')->assertSuccessful();

        $this->assertModelMissing($demo);
        $this->getJson('/api/ubicacion/radiotaxis')
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.nombre', 'Radio Móvil Colcapirhua')
            ->assertJsonPath('features.0.properties.origen', 'monolito')
            ->assertJsonPath('features.0.geometry.coordinates', [-66.24, -17.39]);
    }
}
