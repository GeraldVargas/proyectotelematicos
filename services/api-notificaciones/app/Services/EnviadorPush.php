<?php

namespace App\Services;

use App\Models\EnvioPush;
use Throwable;

class EnviadorPush
{
    public function __construct(private readonly ClienteFcm $fcm) {}

    /**
     * Envía los push vencidos. Un fallo no afecta a la notificación ya guardada: se reintenta
     * con espera creciente hasta max_intentos. Devuelve cuántos se enviaron.
     */
    public function enviarPendientes(int $lote = 100): int
    {
        if (! config('fcm.habilitado')) {
            return 0;
        }

        $envios = EnvioPush::with(['notificacion', 'dispositivo'])
            ->where('estado', 'pendiente')
            ->where(fn ($q) => $q->whereNull('proximo_intento_en')->orWhere('proximo_intento_en', '<=', now()))
            ->orderBy('id')
            ->limit($lote)
            ->get();

        $enviados = 0;

        foreach ($envios as $envio) {
            try {
                $this->fcm->enviar(
                    $envio->dispositivo->token,
                    $envio->notificacion->titulo,
                    $envio->notificacion->mensaje,
                    $this->datos($envio),
                );
                $envio->update(['estado' => 'enviado', 'enviado_en' => now(), 'intentos' => $envio->intentos + 1, 'ultimo_error' => null, 'proximo_intento_en' => null]);
                $enviados++;
            } catch (TokenFcmInvalido) {
                // El borrado en cascada también retira este envío y los demás pendientes del token.
                $envio->dispositivo->delete();
            } catch (Throwable $e) {
                $this->registrarFallo($envio, $e);
            }
        }

        return $enviados;
    }

    private function datos(EnvioPush $envio): array
    {
        $notificacion = $envio->notificacion;

        return array_map('strval', array_filter([
            'notificacion_id' => $notificacion->id,
            'tipo' => $notificacion->tipo,
            'evento_id' => $notificacion->evento_id,
            'ruta_id' => $notificacion->datos['ruta_id'] ?? null,
            'linea' => $notificacion->datos['linea'] ?? null,
        ], fn ($valor) => $valor !== null));
    }

    private function registrarFallo(EnvioPush $envio, Throwable $e): void
    {
        $intentos = $envio->intentos + 1;
        $agotado = $intentos >= config('fcm.max_intentos');

        $envio->update([
            'estado' => $agotado ? 'fallido' : 'pendiente',
            'intentos' => $intentos,
            'ultimo_error' => mb_substr($e->getMessage(), 0, 1000),
            'proximo_intento_en' => $agotado ? null : now()->addSeconds(5 * 2 ** $intentos),
        ]);

        report($e);
    }
}
