<?php

namespace Tests\Feature;

use App\Models\Dispositivo;
use App\Models\EnvioPush;
use App\Models\Suscripcion;
use App\Services\ClienteFcm;
use App\Services\EnviadorPush;
use App\Services\ProcesadorEventos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushFcmTest extends TestCase
{
    use RefreshDatabase;

    private const URL_FCM = 'http://fcm.test/v1/projects/proyecto-test/messages:send';

    private const TOKEN = 'token-fcm-del-dispositivo-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        config(['fcm.habilitado' => true, 'fcm.url' => 'http://fcm.test', 'fcm.proyecto' => 'proyecto-test', 'fcm.credenciales' => null, 'fcm.max_intentos' => 3]);
    }

    public function test_registrar_dispositivo_es_idempotente(): void
    {
        $datos = ['token' => self::TOKEN, 'plataforma' => 'android'];

        $this->postJson('/api/notificaciones/dispositivos', $datos, ['X-User-Id' => '5'])->assertCreated()->assertJsonMissingPath('token');
        $this->postJson('/api/notificaciones/dispositivos', $datos, ['X-User-Id' => '5'])->assertOk();

        $this->assertDatabaseCount('dispositivos', 1);
    }

    public function test_el_token_pasa_a_la_ultima_cuenta_sin_heredar_push_pendientes(): void
    {
        $this->notificarLinea201(['usuario' => 5]);
        $this->assertDatabaseCount('envios_push', 1);

        $this->postJson('/api/notificaciones/dispositivos', ['token' => self::TOKEN], ['X-User-Id' => '9'])->assertCreated();

        $this->assertDatabaseHas('dispositivos', ['token' => self::TOKEN, 'user_id' => 9]);
        $this->assertDatabaseCount('envios_push', 0);
    }

    public function test_validacion_y_autenticacion_de_dispositivos(): void
    {
        $this->postJson('/api/notificaciones/dispositivos', ['token' => self::TOKEN])->assertUnauthorized();
        $this->postJson('/api/notificaciones/dispositivos', ['token' => 'corto'], ['X-User-Id' => '5'])->assertUnprocessable();
        $this->postJson('/api/notificaciones/dispositivos', ['token' => self::TOKEN, 'plataforma' => 'nokia'], ['X-User-Id' => '5'])->assertUnprocessable();
    }

    public function test_solo_el_dueno_elimina_su_dispositivo(): void
    {
        Dispositivo::create(['user_id' => 5, 'token' => self::TOKEN]);

        $this->deleteJson('/api/notificaciones/dispositivos', ['token' => self::TOKEN], ['X-User-Id' => '6'])->assertNotFound();
        $this->assertDatabaseCount('dispositivos', 1);

        $this->deleteJson('/api/notificaciones/dispositivos', ['token' => self::TOKEN], ['X-User-Id' => '5'])->assertNoContent();
        $this->assertDatabaseCount('dispositivos', 0);
    }

    public function test_evento_envia_push_a_todos_los_dispositivos_suscritos(): void
    {
        Http::fake([self::URL_FCM => Http::response(['name' => 'projects/proyecto-test/messages/1'])]);
        Dispositivo::create(['user_id' => 5, 'token' => self::TOKEN.'-b']);
        Dispositivo::create(['user_id' => 77, 'token' => 'token-de-un-usuario-no-suscrito']);
        $this->notificarLinea201(['usuario' => 5]);

        $this->assertSame(2, app(EnviadorPush::class)->enviarPendientes());

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['message']['token'] === self::TOKEN
            && $r['message']['notification']['title'] === 'Cambio en la línea 201'
            && $r['message']['notification']['body'] === 'Desvío por obras'
            && $r['message']['data']['linea'] === '201'
            && $r['message']['data']['ruta_id'] === '3'
            && $r['message']['android']['priority'] === 'high'
            && ! $r->hasHeader('Authorization'));
        $this->assertSame(2, EnvioPush::where('estado', 'enviado')->count());

        // Ya enviados: no se repiten
        $this->assertSame(0, app(EnviadorPush::class)->enviarPendientes());
        Http::assertSentCount(2);
    }

    public function test_reprocesar_el_evento_no_duplica_el_push(): void
    {
        $evento = $this->notificarLinea201(['usuario' => 5]);

        app(ProcesadorEventos::class)->procesar($evento);

        $this->assertDatabaseCount('envios_push', 1);
    }

    public function test_sin_push_habilitado_solo_se_guarda_la_notificacion(): void
    {
        config(['fcm.habilitado' => false]);
        Http::fake();

        $this->notificarLinea201(['usuario' => 5]);

        $this->assertDatabaseCount('notificaciones', 1);
        $this->assertDatabaseCount('envios_push', 0);
        $this->assertSame(0, app(EnviadorPush::class)->enviarPendientes());
        Http::assertNothingSent();
    }

    public function test_error_temporal_reintenta_con_espera_y_luego_entrega(): void
    {
        Http::fake([self::URL_FCM => Http::sequence()->push(['error' => 'no disponible'], 503)->push(['name' => 'ok'])]);
        $this->notificarLinea201(['usuario' => 5]);
        $enviador = app(EnviadorPush::class);

        $this->assertSame(0, $enviador->enviarPendientes());
        $this->assertDatabaseHas('notificaciones', ['user_id' => 5]);
        $envio = EnvioPush::sole();
        $this->assertSame('pendiente', $envio->estado);
        $this->assertSame(1, $envio->intentos);
        $this->assertStringContainsString('503', $envio->ultimo_error);

        // Antes de que venza la espera no se vuelve a llamar a FCM
        $this->assertSame(0, $enviador->enviarPendientes());
        Http::assertSentCount(1);

        $this->travel(11)->seconds();
        $this->assertSame(1, $enviador->enviarPendientes());
        $this->assertSame('enviado', $envio->fresh()->estado);
        $this->assertNull($envio->fresh()->ultimo_error);
    }

    public function test_tras_agotar_los_intentos_queda_fallido(): void
    {
        Http::fake([self::URL_FCM => Http::response('error', 500)]);
        $this->notificarLinea201(['usuario' => 5]);
        $enviador = app(EnviadorPush::class);

        foreach (range(1, 3) as $_) {
            $enviador->enviarPendientes();
            $this->travel(1)->hours();
        }
        $enviador->enviarPendientes();

        Http::assertSentCount(3);
        $this->assertSame('fallido', EnvioPush::sole()->estado);
    }

    public function test_token_no_registrado_elimina_el_dispositivo(): void
    {
        Http::fake([self::URL_FCM => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);
        $this->notificarLinea201(['usuario' => 5]);

        $this->assertSame(0, app(EnviadorPush::class)->enviarPendientes());

        $this->assertDatabaseCount('dispositivos', 0);
        $this->assertDatabaseCount('envios_push', 0);
        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_con_cuenta_de_servicio_se_autentica_por_oauth_y_reutiliza_el_token(): void
    {
        $clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($clave, $privada);
        $ruta = tempnam(sys_get_temp_dir(), 'firebase');
        file_put_contents($ruta, json_encode(['client_email' => 'push@proyecto.iam.gserviceaccount.com', 'private_key' => $privada, 'token_uri' => 'http://oauth.test/token']));
        config(['fcm.credenciales' => $ruta]);
        Http::fake([
            'http://oauth.test/token' => Http::response(['access_token' => 'oauth-123', 'expires_in' => 3600]),
            self::URL_FCM => Http::response(['name' => 'ok']),
        ]);

        $cliente = new ClienteFcm;
        $cliente->enviar(self::TOKEN, 'T', 'C', []);
        $cliente->enviar(self::TOKEN, 'T', 'C', []);
        unlink($ruta);

        Http::assertSent(function (Request $r) use ($clave) {
            if ($r->url() !== 'http://oauth.test/token') {
                return false;
            }
            [$cabecera, $claims, $firma] = explode('.', $r['assertion']);
            $decodificar = fn ($v) => base64_decode(strtr($v, '-_', '+/'));
            $datos = json_decode($decodificar($claims), true);

            return $datos['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && openssl_verify("{$cabecera}.{$claims}", $decodificar($firma), openssl_pkey_get_details($clave)['key'], OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(fn (Request $r) => $r->url() === self::URL_FCM && $r->hasHeader('Authorization', 'Bearer oauth-123'));
        // 1 OAuth + 2 envíos: el access token se reutiliza
        Http::assertSentCount(3);
    }

    /** Suscribe y registra un dispositivo, y procesa un evento de la línea 201. */
    private function notificarLinea201(array $opciones): array
    {
        Suscripcion::create(['user_id' => $opciones['usuario'], 'linea' => '201']);
        Dispositivo::firstOrCreate(['token' => self::TOKEN], ['user_id' => $opciones['usuario']]);

        $evento = [
            'id' => (string) Str::uuid(),
            'tipo' => 'ruta.actualizada',
            'version' => 1,
            'origen' => 'api-ubicacion',
            'ocurrido_en' => now()->toIso8601String(),
            'datos' => ['ruta_id' => 3, 'linea' => '201', 'cambio' => 'trazado', 'descripcion' => 'Desvío por obras'],
        ];
        app(ProcesadorEventos::class)->procesar($evento);

        return $evento;
    }
}
