<?php

namespace App\Services;

use RuntimeException;

/** FCM indica que el token ya no pertenece a una instalación activa de la app. */
class TokenFcmInvalido extends RuntimeException {}
