<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copia los usuarios de la BD única del monolito a la BD de Usuarios.
 * Los hashes bcrypt se copian tal cual: ambos sistemas usan el Hash de Laravel.
 */
class MigrarUsuariosMonolito extends Command
{
    protected $signature = 'usuarios:migrar-monolito
        {--email=* : Migra solo estos correos (para probar con 3-5 usuarios antes de migrar todo)}
        {--dry-run : Muestra lo que se migraría sin escribir nada}';

    protected $description = 'Migra los usuarios del monolito ColcaTrufis (conexión "monolito") a este servicio';

    public function handle(): int
    {
        $consulta = DB::connection('monolito')->table('users')
            ->select(['id', 'name', 'email', 'email_verified_at', 'password', 'rol', 'activo', 'created_at', 'updated_at'])
            ->orderBy('id');

        if ($emails = $this->option('email')) {
            $consulta->whereIn('email', $emails);
        }

        $origen = $consulta->get();
        $dryRun = (bool) $this->option('dry-run');
        $migrados = 0;

        foreach ($origen as $fila) {
            $rol = in_array($fila->rol, User::ROLES, true) ? $fila->rol : 'encargado';

            $this->line(sprintf('%s %s (%s)', $dryRun ? '[dry-run]' : '→', $fila->email, $rol));

            if ($dryRun) {
                continue;
            }

            // Se conserva el id para que el claim sub sea el mismo que en el monolito
            DB::table('users')->updateOrInsert(['email' => $fila->email], [
                'id' => $fila->id,
                'name' => $fila->name,
                'email_verified_at' => $fila->email_verified_at,
                'password' => $fila->password,
                'rol' => $rol,
                'activo' => (bool) $fila->activo,
                'created_at' => $fila->created_at,
                'updated_at' => $fila->updated_at,
            ]);
            $migrados++;
        }

        if (! $dryRun && DB::connection()->getDriverName() === 'mysql') {
            // Que los registros nuevos no choquen con los ids copiados
            $siguiente = (int) DB::table('users')->max('id') + 1;
            DB::statement("ALTER TABLE users AUTO_INCREMENT = {$siguiente}");
        }

        $this->info("Usuarios leídos: {$origen->count()}; migrados: {$migrados}");

        return self::SUCCESS;
    }
}
