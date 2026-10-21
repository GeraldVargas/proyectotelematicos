<?php

namespace App\Console\Commands;

use App\Services\JwtService;
use Illuminate\Console\Command;

class GenerarClavesJwt extends Command
{
    protected $signature = 'jwt:claves {--forzar : Regenera las claves aunque ya existan (invalida todos los tokens)}';

    protected $description = 'Genera el par RSA para firmar JWT y el jwk.json que usa el API Gateway';

    public function handle(JwtService $jwt): int
    {
        if ($jwt->generarClaves((bool) $this->option('forzar'))) {
            $this->info('Claves generadas en '.config('jwt.keys_path'));
        } else {
            $this->line('Las claves ya existen en '.config('jwt.keys_path').'; no se modificaron.');
        }

        return self::SUCCESS;
    }
}
