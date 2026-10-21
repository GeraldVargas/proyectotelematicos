<?php

namespace App\Console\Commands;

use App\Services\ProcesadorEventos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ConsumirEventos extends Command
{
    protected $signature = 'eventos:consumir {--una-vez : Procesa como máximo un evento y termina}';

    protected $description = 'Consume los eventos de la cola de Redis y genera notificaciones';

    private bool $seguir = true;

    public function handle(ProcesadorEventos $procesador): int
    {
        $cola = config('eventos.cola');
        $redis = Redis::connection(config('eventos.conexion_redis'));

        $this->trap([SIGTERM, SIGINT], fn () => $this->seguir = false);
        $this->info("Escuchando eventos en {$cola}");

        while ($this->seguir) {
            // BRPOP con timeout para poder atender SIGTERM
            $item = $redis->brpop([$cola], 5);

            if (! $item) {
                if ($this->option('una-vez')) {
                    break;
                }

                continue;
            }

            $this->procesar($procesador, $item[1]);

            if ($this->option('una-vez')) {
                break;
            }
        }

        return self::SUCCESS;
    }

    private function procesar(ProcesadorEventos $procesador, string $json): void
    {
        try {
            $evento = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $creadas = $procesador->procesar($evento);
            $this->line("✓ {$evento['tipo']} {$evento['id']}: {$creadas} notificaciones");
        } catch (Throwable $e) {
            // PoC: se registra y se descarta. Una cola de mensajes fallidos queda para un sprint futuro.
            report($e);
            $this->error("✗ Evento descartado: {$e->getMessage()}");
        }
    }
}
