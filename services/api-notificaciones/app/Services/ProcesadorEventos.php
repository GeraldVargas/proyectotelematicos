<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcesadorEventos
{
    /**
     * Persiste el evento y crea las notificaciones para los suscriptores.
     * Devuelve cuántas notificaciones se crearon (0 si el evento ya se había procesado).
     */
    public function procesar(array $evento): int
    {
        foreach (['id', 'tipo', 'origen', 'ocurrido_en', 'datos'] as $campo) {
            if (! array_key_exists($campo, $evento)) {
                throw new InvalidArgumentException("Evento sin el campo {$campo}");
            }
        }

        return DB::transaction(function () use ($evento) {
            if (DB::table('eventos_recibidos')->where('evento_id', $evento['id'])->exists()) {
                return 0;
            }

            $creadas = match ($evento['tipo']) {
                'ruta.actualizada' => $this->rutaActualizada($evento),
                default => 0, // Tipos desconocidos se registran pero no notifican
            };

            DB::table('eventos_recibidos')->insert([
                'evento_id' => $evento['id'],
                'tipo' => $evento['tipo'],
                'origen' => $evento['origen'],
                'payload' => json_encode($evento, JSON_UNESCAPED_UNICODE),
                'notificaciones_creadas' => $creadas,
                'recibido_en' => now(),
            ]);

            return $creadas;
        });
    }

    private function rutaActualizada(array $evento): int
    {
        $datos = $evento['datos'];
        $usuarios = Suscripcion::where('linea', $datos['linea'])->pluck('user_id');

        foreach ($usuarios as $userId) {
            Notificacion::create([
                'user_id' => $userId,
                'tipo' => $evento['tipo'],
                'titulo' => "Cambio en la línea {$datos['linea']}",
                'mensaje' => $datos['descripcion'] ?? 'La ruta fue actualizada. Revise el nuevo recorrido en el mapa.',
                'datos' => $datos,
                'evento_id' => $evento['id'],
            ]);
        }

        return $usuarios->count();
    }
}
