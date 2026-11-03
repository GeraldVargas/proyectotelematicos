<?php

namespace App\Console\Commands;

use App\Models\Radiotaxi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Solo para la demo: desplaza los radio taxis de demostración a lo largo de las rutas,
 * porque todavía no hay GPS real. No toca los datos con otro origen.
 */
class SimularRadiotaxis extends Command
{
    protected $signature = 'radiotaxis:simular {--intervalo=15 : Segundos entre movimientos} {--una-vez}';

    protected $description = 'Mueve los radio taxis de demostración sobre las rutas';

    private const VUELTA_SEGUNDOS = 900;

    public function handle(): int
    {
        $seguir = true;
        $this->trap([SIGTERM, SIGINT], function () use (&$seguir) {
            $seguir = false;
        });

        do {
            $this->mover();
            if ($this->option('una-vez')) {
                break;
            }
            sleep(max(1, (int) $this->option('intervalo')));
        } while ($seguir);

        return self::SUCCESS;
    }

    private function mover(): void
    {
        $rutas = DB::table('rutas')->whereNotNull('geom')->orderBy('id')->pluck('id');

        if ($rutas->isEmpty()) {
            return;
        }

        foreach (Radiotaxi::where('origen', 'demo')->where('disponible', true)->orderBy('id')->get() as $i => $radiotaxi) {
            // Cada taxi recorre su ruta asignada, desfasado para que no vayan juntos
            $fraccion = fmod(time() / self::VUELTA_SEGUNDOS + $i * 0.37, 1);
            $punto = DB::selectOne(
                'SELECT ST_X(p) AS lng, ST_Y(p) AS lat FROM (SELECT ST_LineInterpolatePoint(geom, ?) AS p FROM rutas WHERE id = ?) t',
                [$fraccion, $rutas[$i % $rutas->count()]]
            );
            $radiotaxi->moverA($punto->lng, $punto->lat);
        }
    }
}
