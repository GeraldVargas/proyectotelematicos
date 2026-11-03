<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\Suscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuscripcionesYHistorialTest extends TestCase
{
    use RefreshDatabase;

    private const USUARIO = ['X-User-Id' => '7'];

    public function test_desuscribirse_elimina_solo_la_linea_del_usuario(): void
    {
        Suscripcion::create(['user_id' => 7, 'linea' => '252']);
        Suscripcion::create(['user_id' => 7, 'linea' => '201']);
        Suscripcion::create(['user_id' => 8, 'linea' => '252']);

        $this->deleteJson('/api/notificaciones/suscripciones/252', [], self::USUARIO)->assertNoContent();

        $this->assertDatabaseMissing('suscripciones', ['user_id' => 7, 'linea' => '252']);
        $this->assertDatabaseHas('suscripciones', ['user_id' => 7, 'linea' => '201']);
        $this->assertDatabaseHas('suscripciones', ['user_id' => 8, 'linea' => '252']);
    }

    public function test_desuscribirse_de_una_linea_no_suscrita_devuelve_404(): void
    {
        Suscripcion::create(['user_id' => 8, 'linea' => '252']);

        $this->deleteJson('/api/notificaciones/suscripciones/252', [], self::USUARIO)->assertNotFound();
        $this->assertDatabaseCount('suscripciones', 1);
    }

    public function test_suscripciones_exigen_identidad_del_gateway(): void
    {
        $this->deleteJson('/api/notificaciones/suscripciones/252')->assertUnauthorized();
        $this->postJson('/api/notificaciones/suscripciones', ['linea' => '252'])->assertUnauthorized();
        $this->getJson('/api/notificaciones/historial')->assertUnauthorized();
    }

    public function test_historial_filtra_por_fecha_incluyendo_el_dia_final(): void
    {
        $this->notificacion(7, 'Antes', '2026-10-01 12:00:00');
        $this->notificacion(7, 'Primer día', '2026-10-10 00:00:00');
        $this->notificacion(7, 'Último día', '2026-10-20 23:30:00');
        $this->notificacion(7, 'Después', '2026-10-21 00:00:01');
        $this->notificacion(8, 'Ajena', '2026-10-15 12:00:00');

        $res = $this->getJson('/api/notificaciones/historial?desde=2026-10-10&hasta=2026-10-20', self::USUARIO);

        $res->assertOk()->assertJsonPath('total', 2);
        $this->assertSame(['Último día', 'Primer día'], array_column($res->json('data'), 'titulo'));

        $this->getJson('/api/notificaciones/historial?desde=2026-10-21', self::USUARIO)->assertJsonPath('total', 1);
        $this->getJson('/api/notificaciones/historial?hasta=2026-10-01', self::USUARIO)->assertJsonPath('total', 1);
    }

    public function test_historial_respeta_la_hora_si_se_indica(): void
    {
        $this->notificacion(7, 'Mañana', '2026-10-10 08:00:00');
        $this->notificacion(7, 'Tarde', '2026-10-10 18:00:00');

        $this->getJson('/api/notificaciones/historial?hasta='.urlencode('2026-10-10 12:00:00'), self::USUARIO)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.titulo', 'Mañana');
    }

    public function test_historial_pagina(): void
    {
        foreach (range(1, 5) as $i) {
            $this->notificacion(7, "N{$i}", '2026-10-10 12:00:00');
        }

        $this->getJson('/api/notificaciones/historial?por_pagina=2&page=3', self::USUARIO)
            ->assertOk()
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'N1');
    }

    public function test_historial_rechaza_parametros_invalidos(): void
    {
        foreach (['desde=ayer', 'hasta=x', 'desde=2026-10-20&hasta=2026-10-10', 'por_pagina=0', 'por_pagina=101', 'page=0'] as $query) {
            $this->getJson("/api/notificaciones/historial?{$query}", self::USUARIO)->assertUnprocessable();
        }
    }

    private function notificacion(int $userId, string $titulo, string $fecha): void
    {
        $n = Notificacion::create(['user_id' => $userId, 'tipo' => 'x', 'titulo' => $titulo, 'mensaje' => '...']);
        $n->forceFill(['created_at' => $fecha])->save();
    }
}
