<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrarUsuariosMonolitoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Simula la tabla users del monolito en una BD sqlite aparte
        config(['database.connections.monolito' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::connection('monolito')->create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('rol');
            $t->boolean('activo');
            $t->timestamps();
        });
    }

    public function test_usuario_del_monolito_puede_iniciar_sesion_tras_migrar(): void
    {
        DB::connection('monolito')->table('users')->insert([
            ['id' => 7, 'name' => 'Encargada', 'email' => 'encargada@colcatrufis.bo', 'password' => Hash::make('clave-monolito'), 'rol' => 'encargado', 'activo' => true],
            ['id' => 9, 'name' => 'Otro', 'email' => 'otro@colcatrufis.bo', 'password' => Hash::make('x'), 'rol' => 'admin', 'activo' => true],
        ]);

        $this->artisan('usuarios:migrar-monolito', ['--email' => ['encargada@colcatrufis.bo']])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(7, User::first()->id);

        $this->postJson('/api/usuarios/login', ['email' => 'encargada@colcatrufis.bo', 'password' => 'clave-monolito'])
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'encargado');
    }

    public function test_dry_run_no_escribe(): void
    {
        DB::connection('monolito')->table('users')->insert([
            'name' => 'X', 'email' => 'x@colcatrufis.bo', 'password' => 'hash', 'rol' => 'admin', 'activo' => true,
        ]);

        $this->artisan('usuarios:migrar-monolito', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
    }
}
