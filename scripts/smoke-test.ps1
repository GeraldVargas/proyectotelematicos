param(
    [string]$Gateway = 'http://localhost:8080',
    [string]$Panel = 'http://localhost:8081',
    [string]$Swagger = 'http://localhost:8090',
    [string]$ComposeProject = '',
    [string]$ComposeFile = (Join-Path $PSScriptRoot '../docker-compose.yml'),
    [switch]$Sprint2
)
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Gateway = $Gateway.TrimEnd('/')
$checks = 0
$compose = @('compose', '-f', (Resolve-Path $ComposeFile).Path)
if ($ComposeProject) { $compose += @('-p', $ComposeProject) }

function Check($Condition, [string]$Message) {
    if (-not $Condition) { throw "FALLO: $Message" }
    $script:checks++
    Write-Output "OK: $Message"
}

function Request([string]$Path, [string]$Method = 'GET', [hashtable]$Headers = @{}, $Body = $null) {
    $uri = if ($Path -match '^https?://') { $Path } else { "$Gateway$Path" }
    $options = @{ Uri = $uri; Method = $Method; Headers = $Headers; UseBasicParsing = $true; TimeoutSec = 20 }
    if ($null -ne $Body) {
        $options.Body = ConvertTo-Json $Body -Depth 30 -Compress
        $options.ContentType = 'application/json'
    }
    try {
        $r = Invoke-WebRequest @options
        # Windows PowerShell 5 devuelve application/geo+json como bytes.
        $content = if ($r.Content -is [byte[]]) { [Text.Encoding]::UTF8.GetString($r.Content) } else { [string]$r.Content }
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Headers = $r.Headers; Body = $content }
    } catch {
        if ($null -eq $_.Exception.Response) { throw }
        return [pscustomobject]@{ Status = [int]$_.Exception.Response.StatusCode; Headers = $_.Exception.Response.Headers; Body = $_.ErrorDetails.Message }
    }
}

function Cors($Response, [string]$Label) {
    $value = [string]$Response.Headers['Access-Control-Allow-Origin']
    Check ($value -eq '*' -or $value -eq $Panel) "${Label}: CORS sin cabeceras duplicadas"
}

Check ((Request $Panel).Status -eq 200) 'Panel disponible'
Check ((Request $Swagger).Status -eq 200) 'Swagger disponible'
foreach ($spec in @('usuarios', 'ubicacion', 'notificaciones')) {
    Check ((Request "$Swagger/openapi/$spec.yaml").Status -eq 200) "Swagger publica $spec.yaml"
}
$preflight = Request '/api/usuarios/login' 'OPTIONS' @{
    Origin = $Panel; 'Access-Control-Request-Method' = 'POST'; 'Access-Control-Request-Headers' = 'content-type'
}
Check ($preflight.Status -eq 204) 'Preflight del login'
Cors $preflight 'Preflight'
foreach ($path in @('/api/ubicacion/rutas', '/api/usuarios/perfil', '/api/notificaciones')) {
    Check ((Request $path).Status -eq 401) "$path sin token devuelve 401"
}
Check ((Request '/api/ubicacion/rutas' 'GET' @{Authorization = 'Bearer x.y.z'}).Status -eq 401) 'JWT falso rechazado'
Check ((Request '/api/usuarios/login' 'POST' @{} @{email = 'demo@colcatrufis.local'; password = 'incorrecta'}).Status -eq 401) 'Clave incorrecta rechazada'

$login = Request '/api/usuarios/login' 'POST' @{Origin = $Panel} @{email = 'demo@colcatrufis.local'; password = 'demo12345'}
if ($login.Status -ne 200) { throw "Login demo: HTTP $($login.Status). $($login.Body)" }
Check $true 'Login demo'
Cors $login 'Login'
$session = $login.Body | ConvertFrom-Json
Check ([bool]$session.access_token) 'Login emite JWT'
$auth = @{Authorization = "Bearer $($session.access_token)"; Origin = $Panel}
foreach ($path in @('/api/usuarios/perfil', '/api/ubicacion/rutas', '/api/ubicacion/paradas', '/api/notificaciones')) {
    $response = Request $path 'GET' $auth
    Check ($response.Status -eq 200) "$path con JWT devuelve 200"
    Cors $response $path
}
$profile = (Request '/api/usuarios/perfil' 'GET' $auth).Body | ConvertFrom-Json
Check ($profile.email -eq 'demo@colcatrufis.local' -and $profile.rol -eq 'usuario') 'Perfil y rol del usuario demo'
$routes = (Request '/api/ubicacion/rutas' 'GET' $auth).Body | ConvertFrom-Json
Check ($routes.type -eq 'FeatureCollection' -and @($routes.features).Count -ge 3) 'GeoJSON con al menos 3 rutas'
$filtered = (Request '/api/ubicacion/rutas?linea=252' 'GET' $auth).Body | ConvertFrom-Json
Check (@($filtered.features).Count -eq 1) 'Gateway conserva el filtro de linea'
$route = $filtered.features[0]
Check ((Request "/api/ubicacion/rutas/$($route.id)" 'GET' $auth).Status -eq 200) 'Detalle de ruta'
Check ((Request "/api/ubicacion/paradas?ruta_id=$($route.id)" 'GET' $auth).Status -eq 200) 'Paradas filtradas por ruta'

# Un token firmado con la clave local, pero expirado, debe ser rechazado por KrakenD.
$expiredProgram = @'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo app(App\Services\JwtService::class)->crearAccessToken(App\Models\User::where('email', 'demo@colcatrufis.local')->firstOrFail(), time() - 7200);
'@
$expired = $expiredProgram | & docker @compose exec -T api-usuarios php
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el JWT expirado de prueba' }
Check ((Request '/api/ubicacion/rutas' 'GET' @{Authorization = "Bearer $expired"}).Status -eq 401) 'Gateway rechaza JWT firmado y expirado'

$renew = Request '/api/usuarios/refresh' 'POST' @{} @{refresh_token = $session.refresh_token}
Check ($renew.Status -eq 200) 'Refresh de sesion'
Check ((Request '/api/usuarios/refresh' 'POST' @{} @{refresh_token = $session.refresh_token}).Status -eq 401) 'Refresh token anterior no puede reutilizarse'
$subscription = Request '/api/notificaciones/suscripciones' 'POST' $auth @{linea = '252'}
Check ($subscription.Status -in @(200, 201)) 'Suscripcion a linea 252'
$before = ((Request '/api/notificaciones' 'GET' $auth).Body | ConvertFrom-Json).total
& docker @compose exec -T api-ubicacion php artisan eventos:publicar-prueba 252 --descripcion=Verificacion-Sprint-1 | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'No se pudo publicar el evento de prueba' }
$total = $before
for ($i = 0; $i -lt 15 -and $total -le $before; $i++) {
    Start-Sleep -Milliseconds 500
    $total = ((Request '/api/notificaciones' 'GET' $auth).Body | ConvertFrom-Json).total
}
Check ($total -gt $before) 'Evento Ubicacion -> Redis -> Notificaciones'

if ($Sprint2) {
    $stop = ((Request '/api/ubicacion/paradas' 'GET' $auth).Body | ConvertFrom-Json).features[0]
    $lng = $stop.geometry.coordinates[0].ToString([Globalization.CultureInfo]::InvariantCulture)
    $lat = $stop.geometry.coordinates[1].ToString([Globalization.CultureInfo]::InvariantCulture)
    $near = Request "/api/ubicacion/paradas/cercanas?lat=$lat&lng=$lng&radio=100" 'GET' $auth
    Check ($near.Status -eq 200) 'Paradas cercanas por el Gateway'
    $nearby = $near.Body | ConvertFrom-Json
    Check ($nearby.features[0].properties.distancia_m -lt 1) 'Primera parada cercana a menos de un metro'
    $body = @{geometry = $route.geometry; descripcion = 'Verificacion automatica del flujo de edicion de rutas.'}
    $spoof = @{Authorization = $auth.Authorization; 'X-User-Id' = '1'; 'X-User-Rol' = 'admin'}
    Check ((Request "/api/ubicacion/rutas/$($route.id)" 'PUT' $spoof $body).Status -eq 403) 'Usuario no puede editar ni falsificar el rol con headers'
    $admin = (Request '/api/usuarios/login' 'POST' @{} @{email = 'admin@colcatrufis.local'; password = 'admin12345'}).Body | ConvertFrom-Json
    $before = ((Request '/api/notificaciones' 'GET' $auth).Body | ConvertFrom-Json).total
    # Reenviar el mismo trazado verifica la escritura sin cambiar el recorrido de desarrollo.
    $edit = Request "/api/ubicacion/rutas/$($route.id)" 'PUT' @{Authorization = "Bearer $($admin.access_token)"; Origin = $Panel} $body
    Check ($edit.Status -eq 200) 'Administrador puede guardar un trazado'
    Cors $edit 'Edicion de ruta'
    $eventId = ($edit.Body | ConvertFrom-Json).meta.evento_id
    Check ([bool]$eventId) 'Edicion devuelve el UUID del evento persistido'
    $found = $false
    for ($i = 0; $i -lt 20 -and -not $found; $i++) {
        Start-Sleep -Milliseconds 500
        $notifications = (Request '/api/notificaciones' 'GET' $auth).Body | ConvertFrom-Json
        $found = @($notifications.data | Where-Object { $_.evento_id -eq $eventId }).Count -gt 0
    }
    Check $found 'Edicion -> evento persistido -> Redis -> notificacion del suscriptor'
}
Write-Output "Resultado: $checks comprobaciones correctas."
