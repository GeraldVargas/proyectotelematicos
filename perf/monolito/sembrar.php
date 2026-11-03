<?php

// Carga en el esquema del monolito las mismas rutas OSM y los mismos 4 radio taxis de demostración
// que usan los microservicios. No son los datos de producción del municipio.

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::table('trufis')->exists()) {
    echo "Datos ya cargados\n";

    return;
}

// El seeder del monolito no es idempotente: solo se ejecuta la primera vez.
Artisan::call('db:seed', ['--force' => true]);

$datos = json_decode(file_get_contents('/perf/rutas.geojson'), true);
$ahora = now();

foreach ($datos['features'] as $feature) {
    $p = $feature['properties'];
    $sindicato = DB::table('sindicatos')->where('nombre', $p['sindicato'])->value('id')
        ?? DB::table('sindicatos')->insertGetId(['nombre' => $p['sindicato'], 'created_at' => $ahora, 'updated_at' => $ahora]);
    $trufi = DB::table('trufis')->insertGetId([
        'nom_linea' => $p['linea'], 'costo' => 0, 'frecuencia' => 0, 'tipo' => $p['tipo'], 'descripcion' => $p['nombre'],
        'sindicato_id' => $sindicato, 'estado' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    $filas = [];
    foreach ($feature['geometry']['coordinates'] as $i => [$lng, $lat]) {
        $filas[] = ['idtrufi' => $trufi, 'orden' => $i + 1, 'latitud' => $lat, 'longitud' => $lng, 'estado' => 1, 'created_at' => $ahora, 'updated_at' => $ahora];
    }
    foreach (array_chunk($filas, 500) as $lote) {
        DB::table('trufi_rutas')->insert($lote);
    }
}

foreach ([[-66.2405, -17.3895], [-66.2510, -17.3930], [-66.2300, -17.3850], [-66.2450, -17.3990]] as $i => [$lng, $lat]) {
    $radiotaxi = DB::table('sindicato_radiotaxis')->insertGetId([
        'nombre_comercial' => 'Radio Taxi Demo '.($i + 1), 'telefono_base' => '-', 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('sindicato_radiotaxi_paradas')->insert([
        'sindicato_radiotaxi_id' => $radiotaxi, 'latitud' => $lat, 'longitud' => $lng, 'estado' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
}

echo count($datos['features'])." rutas cargadas\n";
