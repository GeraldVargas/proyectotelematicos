<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EdicionDatosClasicosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    private const ADMIN = ['X-User-Id' => '1', 'X-User-Rol' => 'admin'];

    public function test_parada_editada_conserva_relaciones_y_rechaza_datos_invalidos(): void
    {
        $id = DB::table('paradas')->value('id');
        $antes = DB::table('ruta_parada')->where('parada_id', $id)->get()->toJson();
        $datos = ['nombre' => 'Parada actualizada', 'descripcion' => 'Plaza', 'lat' => -17.38, 'lng' => -66.24];
        $url = "/api/ubicacion/paradas/{$id}";
        $this->putJson($url, $datos)->assertUnauthorized();
        $this->putJson($url, $datos, ['X-User-Id' => '2', 'X-User-Rol' => 'usuario'])->assertForbidden();
        $this->putJson($url, $datos, self::ADMIN)->assertOk()
            ->assertJsonPath('features.0.properties.nombre', 'Parada actualizada')
            ->assertJsonPath('features.0.geometry.coordinates', [-66.24, -17.38]);
        $this->assertSame($antes, DB::table('ruta_parada')->where('parada_id', $id)->get()->toJson());
        $datos['lat'] = 91;
        $this->putJson($url, $datos, self::ADMIN)->assertUnprocessable();
    }

    public function test_metadatos_de_ruta_se_publican_con_el_evento_original(): void
    {
        $id = DB::table('rutas')->value('id');
        $original = $this->getJson("/api/ubicacion/rutas/{$id}")->json();
        $this->putJson("/api/ubicacion/rutas/{$id}", [
            'geometry' => $original['geometry'], 'sindicato' => 'Sindicato actualizado',
            'horario' => '06:00–22:00', 'tarifa' => 2.5, 'frecuencia_min' => 10,
            'descripcion' => 'Actualización de los datos de servicio',
        ], self::ADMIN)->assertOk()->assertJsonPath('properties.horario', '06:00–22:00')
            ->assertJsonPath('properties.tarifa', 2.5)->assertJsonPath('meta.notificacion', 'pendiente');
        $this->assertDatabaseCount('eventos_salida', 1);
    }

    public function test_contacto_de_radiotaxi_aparece_en_la_api_del_frontend(): void
    {
        $id = DB::table('radiotaxis')->insertGetId([
            'nombre' => 'Taxi prueba', 'origen' => 'monolito', 'disponible' => true,
        ]);
        $this->putJson("/api/ubicacion/radiotaxis/{$id}/posicion", [
            'nombre' => 'Taxi actualizado', 'telefono' => '77712345',
            'sindicato' => 'Central', 'lat' => -17.38, 'lng' => -66.24,
        ], self::ADMIN)->assertOk()->assertJsonPath('properties.telefono', '77712345');
        $this->getJson('/api/ubicacion/radiotaxis')->assertOk()
            ->assertJsonFragment(['nombre' => 'Taxi actualizado', 'telefono' => '77712345']);
    }
}
