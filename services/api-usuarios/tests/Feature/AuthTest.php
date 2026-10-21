<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_correcto_devuelve_jwt_rs256_con_claims_acordados(): void
    {
        $user = User::factory()->create(['password' => 'secreto123', 'rol' => 'admin']);

        $res = $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123']);

        $res->assertOk()
            ->assertJsonStructure(['token_type', 'access_token', 'expires_in', 'refresh_token', 'usuario' => ['id', 'email', 'rol']]);

        [$cabecera] = explode('.', $res->json('access_token'));
        $header = json_decode(base64_decode(strtr($cabecera, '-_', '+/')), true);
        $this->assertSame('RS256', $header['alg']);
        $this->assertSame(config('jwt.kid'), $header['kid']);

        $claims = app(JwtService::class)->decodificar($res->json('access_token'));
        $this->assertSame((string) $user->id, $claims->sub);
        $this->assertSame('admin', $claims->rol);
        $this->assertGreaterThan(time(), $claims->exp);
    }

    public function test_login_fallido_devuelve_401(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);

        $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'otra-clave'])
            ->assertUnauthorized();

        $this->postJson('/api/usuarios/login', ['email' => 'nadie@colcatrufis.local', 'password' => 'secreto123'])
            ->assertUnauthorized();
    }

    public function test_login_de_usuario_desactivado_devuelve_403(): void
    {
        $user = User::factory()->create(['password' => 'secreto123', 'activo' => false]);

        $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123'])
            ->assertForbidden();
    }

    public function test_token_expirado_es_rechazado(): void
    {
        $user = User::factory()->create();
        $expirado = app(JwtService::class)->crearAccessToken($user, time() - 2 * config('jwt.access_ttl'));

        $this->getJson('/api/usuarios/perfil', ['Authorization' => "Bearer {$expirado}"])
            ->assertUnauthorized();
    }

    public function test_perfil_con_token_valido(): void
    {
        $user = User::factory()->create();
        $token = app(JwtService::class)->crearAccessToken($user);

        $this->getJson('/api/usuarios/perfil', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_perfil_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/usuarios/perfil')->assertUnauthorized();
    }

    public function test_registro_crea_usuario_con_rol_usuario(): void
    {
        $this->postJson('/api/usuarios/registro', [
            'name' => 'Nueva Usuaria',
            'email' => 'nueva@colcatrufis.local',
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
            'rol' => 'admin', // debe ignorarse
        ])->assertCreated()->assertJsonPath('usuario.rol', 'usuario');

        $this->postJson('/api/usuarios/registro', [
            'name' => 'Duplicada',
            'email' => 'nueva@colcatrufis.local',
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
        ])->assertUnprocessable();
    }

    public function test_refresh_rota_el_token_y_no_se_puede_reutilizar(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);
        $refresh = $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123'])
            ->json('refresh_token');

        $nuevo = $this->postJson('/api/usuarios/refresh', ['refresh_token' => $refresh]);
        $nuevo->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        $this->assertNotSame($refresh, $nuevo->json('refresh_token'));

        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $refresh])->assertUnauthorized();
    }

    public function test_refresh_expirado_devuelve_401(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);
        $refresh = $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123'])
            ->json('refresh_token');

        $this->travel(config('jwt.refresh_ttl') + 60)->seconds();

        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $refresh])->assertUnauthorized();
    }
}
