<?php

namespace Tests\Feature;

use App\Models\Parada;
use App\Models\Ruta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrepararDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepara_el_conjunto_completo_en_una_base_vacia(): void
    {
        $this->artisan('demo:preparar')
            ->expectsOutputToContain('22 rutas OSM, 135 paradas OSM y 4 radio taxis demo')
            ->assertSuccessful();

        $this->assertSame(22, Ruta::count());
        $this->assertSame(135, Parada::count());
        $this->assertFalse(Ruta::doesntHave('paradas')->exists());
    }

    public function test_repetir_la_preparacion_conserva_ediciones_y_no_duplica_datos(): void
    {
        $this->seed();
        $ruta = Ruta::where('linea', '252')->firstOrFail();
        $ruta->update(['nombre' => 'Trazado revisado para la presentación']);
        DB::update('UPDATE rutas SET geom = ST_Translate(geom, 0.00001, 0) WHERE id = ?', [$ruta->id]);
        $geometria = DB::table('rutas')->where('id', $ruta->id)->value(DB::raw('ST_AsText(geom)'));

        $this->artisan('demo:preparar')->assertSuccessful();
        $this->artisan('demo:preparar')->assertSuccessful();

        $this->assertSame('Trazado revisado para la presentación', $ruta->fresh()->nombre);
        $this->assertSame($geometria, DB::table('rutas')->where('id', $ruta->id)->value(DB::raw('ST_AsText(geom)')));
        $this->assertSame(22, Ruta::count());
        $this->assertSame(135, Parada::count());
        $this->assertDatabaseCount('radiotaxis', 4);
    }

    public function test_restaura_una_ruta_ausente_y_detecta_paradas_desvinculadas(): void
    {
        $this->seed();
        Ruta::where('linea', '252')->firstOrFail()->delete();
        $this->artisan('demo:preparar')->assertSuccessful();
        $this->assertSame(22, Ruta::count());

        Ruta::where('linea', '252')->firstOrFail()->paradas()->detach();
        $this->artisan('demo:preparar')
            ->expectsOutputToContain('La línea 252 está incompleta')
            ->assertFailed();
    }
}
