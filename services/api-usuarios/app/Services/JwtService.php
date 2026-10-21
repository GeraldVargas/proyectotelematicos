<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use RuntimeException;

class JwtService
{
    public function __construct(
        private readonly string $keysPath,
        private readonly string $kid,
        private readonly string $issuer,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('jwt.keys_path'),
            config('jwt.kid'),
            config('jwt.issuer'),
            config('jwt.access_ttl'),
            config('jwt.refresh_ttl'),
        );
    }

    public function crearAccessToken(User $user, ?int $ahora = null): string
    {
        $ahora ??= time();

        return JWT::encode([
            'iss' => $this->issuer,
            'sub' => (string) $user->id,
            'rol' => $user->rol,
            'iat' => $ahora,
            'exp' => $ahora + $this->accessTtl,
        ], $this->leer('private.pem'), 'RS256', $this->kid);
    }

    /**
     * Devuelve los claims si la firma y la expiración son válidas; lanza excepción si no.
     */
    public function decodificar(string $token): object
    {
        return JWT::decode($token, new Key($this->leer('public.pem'), 'RS256'));
    }

    /**
     * Emite el par access + refresh que reciben los clientes en login, registro y refresh.
     */
    public function emitirPar(User $user): array
    {
        $refresh = Str::random(64);

        $user->refreshTokens()->create([
            'token_hash' => hash('sha256', $refresh),
            'expires_at' => now()->addSeconds($this->refreshTtl),
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $this->crearAccessToken($user),
            'expires_in' => $this->accessTtl,
            'refresh_token' => $refresh,
            'usuario' => $user->toPerfil(),
        ];
    }

    public function buscarRefresh(string $refresh): ?RefreshToken
    {
        return RefreshToken::with('user')
            ->where('token_hash', hash('sha256', $refresh))
            ->first();
    }

    /**
     * Genera el par de claves RSA y el JWK público que lee el API Gateway.
     * No sobrescribe claves existentes salvo que se pida explícitamente.
     */
    public function generarClaves(bool $forzar = false): bool
    {
        if (! $forzar && file_exists($this->ruta('private.pem'))) {
            return false;
        }

        if (! is_dir($this->keysPath) && ! mkdir($this->keysPath, 0755, true)) {
            throw new RuntimeException("No se pudo crear {$this->keysPath}");
        }

        $clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($clave, $privada);
        $detalles = openssl_pkey_get_details($clave);

        file_put_contents($this->ruta('private.pem'), $privada);
        chmod($this->ruta('private.pem'), 0600);
        file_put_contents($this->ruta('public.pem'), $detalles['key']);
        file_put_contents($this->ruta('jwk.json'), json_encode([
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $this->kid,
                'n' => $this->base64url($detalles['rsa']['n']),
                'e' => $this->base64url($detalles['rsa']['e']),
            ]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return true;
    }

    private function leer(string $archivo): string
    {
        $contenido = @file_get_contents($this->ruta($archivo));

        if ($contenido === false) {
            throw new RuntimeException("Falta la clave {$archivo}; ejecute php artisan jwt:claves");
        }

        return $contenido;
    }

    private function ruta(string $archivo): string
    {
        return rtrim($this->keysPath, '/').'/'.$archivo;
    }

    private function base64url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
