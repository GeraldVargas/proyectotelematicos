<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la API HTTP v1 de Firebase Cloud Messaging.
 * Con cuenta de servicio se autentica por OAuth2; contra fcm-mock va sin credenciales.
 */
class ClienteFcm
{
    private ?string $accessToken = null;

    private int $expiraEn = 0;

    /**
     * @param  array<string, string>  $datos  FCM solo admite valores de texto en data
     *
     * @throws TokenFcmInvalido si el dispositivo ya no existe
     * @throws RuntimeException ante un error reintentable
     */
    public function enviar(string $token, string $titulo, string $cuerpo, array $datos): void
    {
        $url = config('fcm.url').'/v1/projects/'.config('fcm.proyecto').'/messages:send';

        $res = Http::timeout(10)
            ->when($this->tokenOAuth(), fn ($http, $oauth) => $http->withToken($oauth))
            ->post($url, ['message' => [
                'token' => $token,
                'notification' => ['title' => $titulo, 'body' => $cuerpo],
                'data' => $datos,
                'android' => ['priority' => 'high'],
            ]]);

        // Un 404 también puede indicar una URL o proyecto incorrectos: solo FCM UNREGISTERED
        // demuestra que hay que retirar el token, sin borrar dispositivos por un error de configuración.
        if ($res->status() === 404 && collect($res->json('error.details', []))->contains('errorCode', 'UNREGISTERED')) {
            throw new TokenFcmInvalido('FCM: token no registrado');
        }

        if (! $res->successful()) {
            throw new RuntimeException("FCM respondió {$res->status()}: ".mb_substr($res->body(), 0, 500));
        }
    }

    private function tokenOAuth(): ?string
    {
        $ruta = config('fcm.credenciales');

        if (! $ruta) {
            return null;
        }

        if ($this->accessToken && time() < $this->expiraEn - 60) {
            return $this->accessToken;
        }

        $cuenta = json_decode((string) @file_get_contents($ruta), true);

        if (! isset($cuenta['client_email'], $cuenta['private_key'], $cuenta['token_uri'])) {
            throw new RuntimeException("Cuenta de servicio de Firebase inválida o ausente: {$ruta}");
        }

        $res = Http::asForm()->timeout(10)->post($cuenta['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion($cuenta),
        ]);

        if (! $res->successful()) {
            throw new RuntimeException("OAuth de Google respondió {$res->status()}");
        }

        $this->accessToken = $res->json('access_token');
        $this->expiraEn = time() + (int) $res->json('expires_in', 3600);

        return $this->accessToken;
    }

    private function assertion(array $cuenta): string
    {
        $ahora = time();
        $partes = array_map(fn (array $parte) => $this->base64url(json_encode($parte)), [
            ['alg' => 'RS256', 'typ' => 'JWT'],
            [
                'iss' => $cuenta['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $cuenta['token_uri'],
                'iat' => $ahora,
                'exp' => $ahora + 3600,
            ],
        ]);
        $sinFirma = implode('.', $partes);

        if (! openssl_sign($sinFirma, $firma, $cuenta['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la solicitud OAuth de Firebase');
        }

        return $sinFirma.'.'.$this->base64url($firma);
    }

    private function base64url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
