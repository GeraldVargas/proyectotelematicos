<?php

namespace App\Console\Commands;

use App\Services\EnviadorPush;
use App\Services\ProcesadorEventos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ConsumirEventos extends Command
{
    protected $signature = 'eventos:consumir {--una-vez : Procesa como máximo un evento y termina}';

    protected $description = 'Consume los eventos de la cola de Redis y genera notificaciones';

    private bool $seguir = true;

    public function handle(ProcesadorEventos $procesador, EnviadorPush $push): int
    {
        $cola = config('eventos.cola');
        $redis = Redis::connection(config('eventos.conexion_redis'));

        $this->trap([SIGTERM, SIGINT], fn () => $this->seguir = false);
        $this->info("Escuchando eventos en {$cola}");

        while ($this->seguir) {
            // BRPOP con timeout para poder atender SIGTERM
            $item = $redis->brpop([$cola], 5);

            if ($item) {
                $this->procesar($procesador, $item[1]);
            }

            // También sin eventos nuevos, para atender los reintentos cuando vencen.
            $this->enviarPush($push);

            if ($this->option('una-vez')) {
                break;
            }
        }

        return self::SUCCESS;
    }

    private function enviarPush(EnviadorPush $push): void
    {
        try {
            $enviados = $push->enviarPendientes();

            if ($enviados > 0) {
                $this->line("✓ Push enviados: {$enviados}");
            }
        } catch (Throwable $e) {
            report($e);
            $this->error("✗ Push: {$e->getMessage()}");
        }
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
