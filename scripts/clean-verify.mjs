// Copia de fuentes sin vendor, node_modules, .dart_tool ni datos locales; BDs nuevas.
// No publica cambios ni modifica el entorno Docker principal.
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, copyFileSync, readFileSync, writeFileSync, appendFileSync } from 'node:fs';
import { stripVTControlCharacters } from 'node:util';
import { createServer } from 'node:net';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import os from 'node:os';

const root = fileURLToPath(new URL('../', import.meta.url));
const temporary = path.join(root, '.verification');
mkdirSync(temporary, { recursive: true });
const snapshot = mkdtempSync(path.join(temporary, 'clean-'));
const project = `colcatrufis-verify-${Date.now()}`;
const git = spawnSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], { cwd: root });
if (git.status !== 0) throw new Error(git.stderr.toString());
for (const relative of new Set(git.stdout.toString('utf8').split('\0').filter(Boolean))) {
  const source = path.resolve(root, relative);
  if (!source.startsWith(path.resolve(root) + path.sep)) throw new Error('Ruta fuera del proyecto');
  const destination = path.join(snapshot, relative);
  mkdirSync(path.dirname(destination), { recursive: true });
  copyFileSync(source, destination);
}
const logfile = path.join(snapshot, 'verification.log');
// Diagnóstico sin hashes ni tokens, solo dentro de la copia desechable.
writeFileSync(path.join(snapshot, 'services/api-usuarios/tests/verify-seed.php'), `<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$user = App\\Models\\User::where('email', 'demo@colcatrufis.local')->first();
echo json_encode(['driver' => Illuminate\\Support\\Facades\\DB::connection()->getDriverName(), 'users' => App\\Models\\User::count(), 'demo' => (bool) $user, 'password_ok' => $user ? Illuminate\\Support\\Facades\\Hash::check('demo12345', $user->password) : false]);
`);
const report = {
  fecha: new Date().toISOString(), estado: 'en curso', proyecto: project,
  origen: 'Fuentes versionables del arbol de trabajo actual; no es un clon remoto ni una descarga sin cache.',
  cacheDocker: 'existente', basesDeDatos: 'volumenes nuevos, separados del entorno principal',
  equipo: { cpu: os.cpus()[0].model, ramGiB: +(os.totalmem() / 1024 ** 3).toFixed(1), so: `${os.type()} ${os.release()}` },
};
async function port() {
  const server = createServer();
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
  const result = server.address().port;
  await new Promise((resolve) => server.close(resolve));
  return result;
}
const ports = new Set();
while (ports.size < 5) ports.add(await port());
const [gateway, panel, swagger, database, fcm] = [...ports];
const environment = { ...process.env, GATEWAY_PORT: String(gateway), ADMIN_WEB_PORT: String(panel), SWAGGER_PORT: String(swagger), UBICACION_DB_PORT: String(database), FCM_MOCK_PORT: String(fcm) };
const compose = ['compose', '-p', project, '-f', path.join(snapshot, 'docker-compose.yml')];
function run(command, args, label) {
  console.log(label);
  return new Promise((resolve, reject) => {
    let output = '';
    const child = spawn(command, args, { cwd: root, env: environment, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    for (const stream of [child.stdout, child.stderr]) stream.on('data', (chunk) => { output += chunk; appendFileSync(logfile, chunk); });
    child.on('error', reject);
    child.on('close', (code) => code === 0 ? resolve(output) : reject(new Error(`${label}: codigo ${code}\n${output.slice(-2500)}`)));
  });
}
const shell = process.platform === 'win32' ? path.join(process.env.SystemRoot, 'System32/WindowsPowerShell/v1.0/powershell.exe') : 'pwsh';
const artifacts = path.join(root, 'docs/qa/artifacts');
mkdirSync(artifacts, { recursive: true });
try {
  const start = Date.now();
  await run('docker', [...compose, 'up', '-d', '--build', '--wait', '--wait-timeout', '240'], 'Construyendo copia limpia con BDs nuevas...');
  report.arranqueSegundos = +((Date.now() - start) / 1000).toFixed(2);
  report.semillaAntesDeTests = JSON.parse(await run('docker', [...compose, 'exec', '-T', 'api-usuarios', 'php', 'tests/verify-seed.php'], 'Verificando semilla inicial...'));
  const results = await Promise.all(['api-usuarios', 'api-ubicacion', 'api-notificaciones'].map(async (service) => {
    const output = await run('docker', [...compose, 'exec', '-T', service, 'php', 'artisan', 'test', '--no-ansi'], `Tests: ${service}`);
    return { servicio: service, resultado: output.match(/Tests:\s+[^\r\n]+/)?.[0] || 'ver registro' };
  }));
  report.tests = results;
  report.semillaDespuesDeTests = JSON.parse(await run('docker', [...compose, 'exec', '-T', 'api-usuarios', 'php', 'tests/verify-seed.php'], 'Verificando aislamiento de los tests...'));
  if (!report.semillaDespuesDeTests.password_ok || report.semillaAntesDeTests.users !== report.semillaDespuesDeTests.users) {
    throw new Error('Las pruebas alteraron la base de desarrollo: revisar el aislamiento antes de continuar.');
  }
  const smoke = await run(shell, ['-NoProfile', ...(process.platform === 'win32' ? ['-ExecutionPolicy', 'Bypass'] : []), '-File', path.join(snapshot, 'scripts/smoke-test.ps1'),
    '-Gateway', `http://localhost:${gateway}`, '-Panel', `http://localhost:${panel}`, '-Swagger', `http://localhost:${swagger}`,
    '-Fcm', `http://localhost:${fcm}`,
    '-ComposeProject', project, '-ComposeFile', path.join(snapshot, 'docker-compose.yml'), '-Sprint2'], 'Smoke completo de la copia limpia...');
  report.smoke = smoke.match(/Resultado: [^\r\n]+/)?.[0];
  report.estado = 'correcto';
  console.log(JSON.stringify(report, null, 2));
} catch (error) {
  report.estado = 'fallo';
  report.error = error.message;
  console.error(error.message);
  await run('docker', [...compose, 'logs', '--no-color', '--tail', '100'], 'Guardando diagnostico del entorno de verificacion...').catch(() => {});
  process.exitCode = 1;
} finally {
  // Esta limpieza solo puede operar sobre el proyecto desechable creado arriba.
  if (/^colcatrufis-verify-\d+$/.test(project) && path.resolve(snapshot).startsWith(path.resolve(temporary) + path.sep)) {
    try { await run('docker', [...compose, 'down', '-v'], 'Retirando solo el entorno desechable de verificacion...'); report.limpieza = 'correcta'; }
    catch (error) { report.limpieza = error.message; process.exitCode = 1; }
  }
  writeFileSync(path.join(artifacts, 'clean-verify.json'), `${JSON.stringify(report, null, 2)}\n`);
  // Conservar el original local y publicar texto legible sin escapes ANSI ni espacios finales.
  const readableLog = stripVTControlCharacters(readFileSync(logfile, 'utf8')).split(/\r?\n/).map((line) => line.trimEnd()).join('\n');
  writeFileSync(path.join(artifacts, 'clean-verify.log'), readableLog);
}
