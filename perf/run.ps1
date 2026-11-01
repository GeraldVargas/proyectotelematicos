param(
    [ValidateSet('monolito', 'microservicios')][string]$Target = 'monolito',
    [string]$BaseUrl = '',
    [int]$Vus = 20,
    [string]$Duracion = '2m',
    [string]$Rampa = '20s',
    [string]$Bajada = '10s',
    [string]$Network = '',
    [switch]$SinRadiotaxis
)
$ErrorActionPreference = 'Stop'
if ($Vus -lt 1) { throw 'Vus debe ser mayor que cero' }
if (-not $BaseUrl) { $BaseUrl = if ($Target -eq 'monolito') { 'http://host.docker.internal:8000' } else { 'http://host.docker.internal:8080' } }
$output = Join-Path $PSScriptRoot 'resultados'
New-Item -ItemType Directory -Force -Path $output | Out-Null
$arguments = @('run', '--rm', '--add-host=host.docker.internal:host-gateway')
if ($Network) { $arguments += @('--network', $Network) }
$arguments += @('--mount', "type=bind,source=$PSScriptRoot/k6,target=/scripts,readonly", '--mount', "type=bind,source=$output,target=/resultados")
foreach ($item in @("TARGET=$Target", "BASE_URL=$BaseUrl", "VUS=$Vus", "DURACION=$Duracion", "RAMPA=$Rampa", "BAJADA=$Bajada")) { $arguments += @('-e', $item) }
if ($SinRadiotaxis) { $arguments += @('-e', 'SIN_RADIOTAXIS=1') }
foreach ($name in @('EMAIL', 'PASSWORD')) {
    $value = [Environment]::GetEnvironmentVariable($name)
    if ($value) { $arguments += @('-e', "$name=$value") }
}
& docker @arguments grafana/k6:0.54.0 run /scripts/escenarios.js
if ($LASTEXITCODE -ne 0) { throw "k6 termino con codigo $LASTEXITCODE; consulta los resultados y umbrales." }
