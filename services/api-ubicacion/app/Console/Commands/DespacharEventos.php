<?php

namespace App\Console\Commands;

use App\Services\DespachadorEventos;
use Illuminate\Console\Command;

class DespacharEventos extends Command
{
    protected $signature = 'eventos:despachar {--una-vez : Procesa el lote pendiente y termina}';

    protected $description = 'Reenvía a Redis los eventos persistidos al editar una ruta';

    public function handle(DespachadorEventos $despachador): int
    {
        $seguir = true;
        $this->trap([SIGTERM, SIGINT], function () use (&$seguir) {
            $seguir = false;
        });
        do {
            $enviados = $despachador->despacharPendientes();
            if ($enviados > 0) {
                $this->info("Eventos enviados: {$enviados}");
            }
            if ($this->option('una-vez')) {
                break;
            }
            sleep(2);
        } while ($seguir);

        return self::SUCCESS;
    }
}
