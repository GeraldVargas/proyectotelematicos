<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\Suscripcion;
use App\Services\ProcesadorEventos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_header_del_gateway_devuelve_401(): void
    {
        $this->getJson('/api/notificaciones')->assertUnauthorized();
    }

    public function test_lista_solo_las_notificaciones_del_usuario_del_jwt(): void
    {
        Notificacion::create(['user_id' => 1, 'tipo' => 'x', 'titulo' => 'Mía', 'mensaje' => '...']);
        Notificacion::create(['user_id' => 2, 'tipo' => 'x', 'titulo' => 'Ajena', 'mensaje' => '...']);

        $this->getJson('/api/notificaciones', ['X-User-Id' => '1'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Mía');
    }

    public function test_suscribirse_a_una_linea(): void
    {
        $this->postJson('/api/notificaciones/suscripciones', ['linea' => '252'], ['X-User-Id' => '5'])->assertCreated();
        $this->postJson('/api/notificaciones/suscripciones', ['linea' => '252'], ['X-User-Id' => '5'])->assertOk();

        $this->getJson('/api/notificaciones/suscripciones', ['X-User-Id' => '5'])
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.linea', '252');
    }

    public function test_evento_ruta_actualizada_notifica_a_los_suscriptores_una_sola_vez(): void
    {
        Suscripcion::create(['user_id' => 10, 'linea' => '201']);
        Suscripcion::create(['user_id' => 11, 'linea' => '201']);
        Suscripcion::create(['user_id' => 12, 'linea' => '290']);

        $evento = $this->eventoDePrueba('201');
        $procesador = app(ProcesadorEventos::class);

        $this->assertSame(2, $procesador->procesar($evento));
        $this->assertSame(0, $procesador->procesar($evento), 'Reprocesar el mismo evento no debe duplicar');

        $this->assertDatabaseCount('notificaciones', 2);
        $this->assertDatabaseHas('eventos_recibidos', ['evento_id' => $evento['id'], 'notificaciones_creadas' => 2]);

        $this->getJson('/api/notificaciones', ['X-User-Id' => '10'])
            ->assertJsonPath('data.0.titulo', 'Cambio en la línea 201')
            ->assertJsonPath('data.0.datos.ruta_id', 3);
    }

    public function test_evento_incompleto_es_rechazado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ProcesadorEventos::class)->procesar(['tipo' => 'ruta.actualizada']);
    }

    private function eventoDePrueba(string $linea): array
    {
        // Mismo formato que docs/eventos/ruta.actualizada.json
        return [
            'id' => (string) Str::uuid(),
            'tipo' => 'ruta.actualizada',
            'version' => 1,
            'origen' => 'api-ubicacion',
            'ocurrido_en' => now()->toIso8601String(),
            'datos' => ['ruta_id' => 3, 'linea' => $linea, 'cambio' => 'trazado', 'descripcion' => 'Desvío por obras'],
        ];
    }
}
