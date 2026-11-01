<?php

namespace Tests\Feature;

use App\Services\DespachadorEventos;
use App\Services\PublicadorEventos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EdicionRutaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const ADMIN = ['X-User-Id' => '1', 'X-User-Rol' => 'admin'];

    private function payload(): array
    {
        return [
            'geometry' => ['type' => 'LineString', 'coordinates' => [[-66.24, -17.38], [-66.23, -17.39]]],
            'nombre' => 'Recorrido actualizado', 'estado' => 'suspendida',
            'descripcion' => 'Desvío temporal por mantenimiento de la vía.',
        ];
    }

    private function idRuta(): int
    {
        return DB::table('rutas')->where('linea', '252')->value('id');
    }

    public function test_admin_actualiza_geometria_y_persiste_evento_en_la_misma_transaccion(): void
    {
        $id = $this->idRuta();
        $res = $this->putJson("/api/ubicacion/rutas/{$id}", $this->payload(), self::ADMIN);
        $res->assertOk()->assertJsonPath('geometry.coordinates', $this->payload()['geometry']['coordinates'])
            ->assertJsonPath('properties.estado', 'suspendida')->assertJsonPath('meta.notificacion', 'pendiente');
        $evento = DB::table('eventos_salida')->where('id', $res->json('meta.evento_id'))->first();
        $this->assertNull($evento->publicado_en);
        $payload = json_decode($evento->payload, true);
        $this->assertSame('ruta.actualizada', $payload['tipo']);
        $this->assertSame('252', $payload['datos']['linea']);
        $this->assertSame($id, $payload['datos']['ruta_id']);
        $this->assertDatabaseCount('eventos_salida', 1);
    }

    public function test_usuario_y_encargado_no_pueden_editar(): void
    {
        foreach (['usuario', 'encargado'] as $rol) {
            $this->putJson('/api/ubicacion/rutas/'.$this->idRuta(), $this->payload(), ['X-User-Id' => '2', 'X-User-Rol' => $rol])
                ->assertForbidden();
        }
        $this->assertDatabaseCount('eventos_salida', 0);
    }

    public function test_sin_identidad_del_gateway_no_se_permite_editar(): void
    {
        $this->putJson('/api/ubicacion/rutas/'.$this->idRuta(), $this->payload())->assertUnauthorized();
        $this->putJson('/api/ubicacion/rutas/'.$this->idRuta(), $this->payload(), ['X-User-Id' => 'abc', 'X-User-Rol' => 'admin'])
            ->assertUnauthorized();
    }

    public function test_geometrias_invalidas_no_modifican_la_ruta_ni_generan_eventos(): void
    {
        $id = $this->idRuta();
        $original = $this->getJson("/api/ubicacion/rutas/{$id}")->json('geometry');
        foreach ([
            ['type' => 'Point', 'coordinates' => [-66.24, -17.38]],
            ['type' => 'LineString', 'coordinates' => [[-66.24, -17.38]]],
            ['type' => 'LineString', 'coordinates' => [[181, -17.38], [-66.23, -17.39]]],
            ['type' => 'LineString', 'coordinates' => [[-66.24, 91], [-66.23, -17.39]]],
            ['type' => 'LineString', 'coordinates' => [[-66.24, -17.38], [-66.24, -17.38]]],
            ['type' => 'LineString', 'coordinates' => 'no es un array'],
            ['type' => 'LineString', 'coordinates' => ['inicio' => [-66.24, -17.38], 'fin' => [-66.23, -17.39]]],
        ] as $geometry) {
            $body = $this->payload();
            $body['geometry'] = $geometry;
            $this->putJson("/api/ubicacion/rutas/{$id}", $body, self::ADMIN)->assertUnprocessable();
        }
        $this->assertSame($original, $this->getJson("/api/ubicacion/rutas/{$id}")->json('geometry'));
        $this->assertDatabaseCount('eventos_salida', 0);
    }

    public function test_ruta_inexistente_y_descripcion_vacia(): void
    {
        $this->putJson('/api/ubicacion/rutas/999999', $this->payload(), self::ADMIN)->assertNotFound();
        $body = $this->payload();
        $body['descripcion'] = '';
        $this->putJson('/api/ubicacion/rutas/'.$this->idRuta(), $body, self::ADMIN)->assertUnprocessable();
    }

    public function test_reiniciar_el_seeder_conserva_las_ediciones(): void
    {
        $id = $this->idRuta();
        $this->putJson("/api/ubicacion/rutas/{$id}", $this->payload(), self::ADMIN)->assertOk();
        $this->seed();
        $this->getJson("/api/ubicacion/rutas/{$id}")->assertJsonPath('properties.nombre', 'Recorrido actualizado')
            ->assertJsonPath('geometry.coordinates', $this->payload()['geometry']['coordinates']);
    }

    public function test_fallo_al_crear_el_evento_revierte_la_edicion(): void
    {
        $id = $this->idRuta();
        $original = $this->getJson("/api/ubicacion/rutas/{$id}")->json();
        $publicador = Mockery::mock(PublicadorEventos::class);
        $publicador->shouldReceive('preparar')->once()->andThrow(new RuntimeException('No se pudo preparar el evento'));
        $this->app->instance(PublicadorEventos::class, $publicador);
        $this->putJson("/api/ubicacion/rutas/{$id}", $this->payload(), self::ADMIN)->assertStatus(500);
        $this->assertSame($original, $this->getJson("/api/ubicacion/rutas/{$id}")->json());
        $this->assertDatabaseCount('eventos_salida', 0);
    }

    public function test_despachador_reintenta_sin_perder_el_uuid_y_no_reenvia_publicados(): void
    {
        $res = $this->putJson('/api/ubicacion/rutas/'.$this->idRuta(), $this->payload(), self::ADMIN)->assertOk();
        $id = $res->json('meta.evento_id');
        $caido = Mockery::mock(PublicadorEventos::class);
        $caido->shouldReceive('enviar')->once()->andThrow(new RuntimeException('Redis no disponible (prueba)'));
        $this->assertSame(0, (new DespachadorEventos($caido))->despacharPendientes());
        $this->assertDatabaseHas('eventos_salida', ['id' => $id, 'intentos' => 1, 'publicado_en' => null]);
        $restaurado = Mockery::mock(PublicadorEventos::class);
        $restaurado->shouldReceive('enviar')->once()->withArgs(fn ($e) => $e['id'] === $id && $e['datos']['linea'] === '252');
        $despachador = new DespachadorEventos($restaurado);
        $this->assertSame(1, $despachador->despacharPendientes());
        $this->assertSame(0, $despachador->despacharPendientes());
        $fila = DB::table('eventos_salida')->where('id', $id)->first();
        $this->assertNotNull($fila->publicado_en);
        $this->assertSame(2, $fila->intentos);
        $this->assertNull($fila->ultimo_error);
    }
}
