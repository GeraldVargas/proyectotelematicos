<?php

// Simula la API HTTP v1 de FCM para probar el push en local sin credenciales de Firebase.
//   POST   /v1/projects/{proyecto}/messages:send  guarda el mensaje; un token "invalido-..." responde 404 UNREGISTERED
//   GET    /mensajes                              lista lo recibido
//   DELETE /mensajes                              vacía la lista
//   GET    /salud

const ARCHIVO = '/tmp/mensajes.jsonl';

function responder(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'];

if ($ruta === '/salud') {
    responder(200, ['estado' => 'ok']);
}

if ($ruta === '/mensajes' && $metodo === 'GET') {
    $lineas = file_exists(ARCHIVO) ? file(ARCHIVO, FILE_IGNORE_NEW_LINES) : [];
    responder(200, ['total' => count($lineas), 'mensajes' => array_map(fn ($l) => json_decode($l, true), $lineas)]);
}

if ($ruta === '/mensajes' && $metodo === 'DELETE') {
    @unlink(ARCHIVO);
    responder(200, ['total' => 0]);
}

if ($metodo === 'POST' && preg_match('#^/v1/projects/([^/]+)/messages:send$#', $ruta, $m)) {
    $cuerpo = json_decode(file_get_contents('php://input'), true);
    $mensaje = $cuerpo['message'] ?? null;

    if (empty($mensaje['token']) || empty($mensaje['notification']['title'])) {
        responder(400, ['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Falta message.token o notification.title']]);
    }

    foreach ($mensaje['data'] ?? [] as $valor) {
        if (! is_string($valor)) {
            responder(400, ['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Los valores de data deben ser texto']]);
        }
    }

    if (str_starts_with($mensaje['token'], 'invalido-')) {
        responder(404, ['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]);
    }

    file_put_contents(ARCHIVO, json_encode($mensaje, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND | LOCK_EX);
    responder(200, ['name' => "projects/{$m[1]}/messages/".bin2hex(random_bytes(8))]);
}

responder(404, ['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Ruta desconocida']]);
