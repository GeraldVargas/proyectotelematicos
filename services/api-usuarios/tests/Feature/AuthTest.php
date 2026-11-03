<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    public function test_dos_solicitudes_que_leyeron_el_mismo_refresh_solo_emiten_un_par(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);
        $jwt = app(JwtService::class);
        $refresh = $jwt->emitirPar($user)['refresh_token'];
        $lecturaAnterior = $jwt->buscarRefresh($refresh);

        // Ambas solicitudes ya leyeron el token vigente; la segunda conserva esa lectura
        // aunque la primera lo haya consumido en la base de datos.
        $jwtConLecturaAnterior = Mockery::mock($jwt)->makePartial();
        $jwtConLecturaAnterior->shouldReceive('buscarRefresh')->with($refresh)->twice()->andReturn($lecturaAnterior);
        $this->app->instance(JwtService::class, $jwtConLecturaAnterior);

        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $refresh])->assertOk();
        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $refresh])->assertUnauthorized();

        $this->assertSame(1, $user->refreshTokens()->whereNull('revoked_at')->count());
        $this->assertDatabaseCount('refresh_tokens', 2);
    }

    public function test_logout_revoca_el_refresh_token_de_la_sesion(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);
        $sesion = $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123'])->json();
        $otra = $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123'])->json();

        $this->deleteJson('/api/usuarios/sesion', ['refresh_token' => $sesion['refresh_token']], $this->bearer($user))
            ->assertNoContent();

        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $sesion['refresh_token']])->assertUnauthorized();
        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $otra['refresh_token']])->assertOk();
    }

    public function test_logout_sin_refresh_token_cierra_todas_las_sesiones(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);
        $tokens = collect(range(1, 2))->map(fn () => $this->postJson('/api/usuarios/login', ['email' => $user->email, 'password' => 'secreto123'])->json('refresh_token'));

        $this->deleteJson('/api/usuarios/sesion', [], $this->bearer($user))->assertNoContent();

        $tokens->each(fn ($t) => $this->postJson('/api/usuarios/refresh', ['refresh_token' => $t])->assertUnauthorized());
    }

    public function test_logout_no_revoca_sesiones_de_otro_usuario(): void
    {
        $ajeno = User::factory()->create(['password' => 'secreto123']);
        $refresh = $this->postJson('/api/usuarios/login', ['email' => $ajeno->email, 'password' => 'secreto123'])->json('refresh_token');

        $this->deleteJson('/api/usuarios/sesion', ['refresh_token' => $refresh], $this->bearer(User::factory()->create()))
            ->assertNoContent();

        $this->postJson('/api/usuarios/refresh', ['refresh_token' => $refresh])->assertOk();
    }

    public function test_logout_sin_token_devuelve_401(): void
    {
        $this->deleteJson('/api/usuarios/sesion')->assertUnauthorized();
    }

    public function test_usuario_solo_puede_ver_su_propio_perfil_por_id(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $this->getJson("/api/usuarios/perfil/{$user->id}", $this->bearer($user))->assertOk()->assertJsonPath('id', $user->id);
        $this->getJson("/api/usuarios/perfil/{$otro->id}", $this->bearer($user))->assertForbidden();
    }

    public function test_admin_y_encargado_ven_cualquier_perfil_por_id(): void
    {
        $otro = User::factory()->create();

        foreach (['admin', 'encargado'] as $rol) {
            $this->getJson("/api/usuarios/perfil/{$otro->id}", $this->bearer(User::factory()->create(['rol' => $rol])))
                ->assertOk()
                ->assertJsonPath('email', $otro->email)
                ->assertJsonMissingPath('password');
        }

        $this->getJson('/api/usuarios/perfil/999999', $this->bearer(User::factory()->create(['rol' => 'admin'])))->assertNotFound();
    }

    public function test_perfil_ajeno_inexistente_no_revela_su_existencia_a_un_usuario(): void
    {
        $this->getJson('/api/usuarios/perfil/999999', $this->bearer(User::factory()->create()))->assertForbidden();
    }

    public function test_lista_de_usuarios_solo_para_admin(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        User::factory()->count(2)->create();

        $this->getJson('/api/usuarios/lista', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonStructure(['current_page', 'per_page', 'total', 'last_page', 'data' => [['id', 'name', 'email', 'rol', 'activo']]])
            ->assertJsonMissingPath('data.0.password');

        foreach (['usuario', 'encargado'] as $rol) {
            $this->getJson('/api/usuarios/lista', $this->bearer(User::factory()->create(['rol' => $rol])))->assertForbidden();
        }

        $this->getJson('/api/usuarios/lista')->assertUnauthorized();
    }

    public function test_lista_de_usuarios_pagina_y_valida_parametros(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        User::factory()->count(4)->create();

        $this->getJson('/api/usuarios/lista?por_pagina=2&page=3', $this->bearer($admin))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('last_page', 3);

        foreach (['por_pagina=0', 'por_pagina=101', 'page=0'] as $query) {
            $this->getJson("/api/usuarios/lista?{$query}", $this->bearer($admin))->assertUnprocessable();
        }
    }

    public function test_usuario_desactivado_pierde_acceso_aunque_su_jwt_siga_vigente(): void
    {
        $user = User::factory()->create(['rol' => 'admin']);
        $cabeceras = $this->bearer($user);
        $user->update(['activo' => false]);

        $this->getJson('/api/usuarios/lista', $cabeceras)->assertUnauthorized();
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->crearAccessToken($user)];
    }
}
