import { Client } from 'basic-ftp';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const envDeployPath = join(root, '.env.deploy');

if (!existsSync(envDeployPath)) {
  console.error('Fehler: .env.deploy fehlt. Bitte .env.deploy.example kopieren und ausfuellen.');
  process.exit(1);
}

process.loadEnvFile(envDeployPath);

const ftpHost = process.env.FTP_HOST ?? '';
const ftpUser = process.env.FTP_USER ?? '';
const ftpPassword = process.env.FTP_PASSWORD ?? '';
const ftpPort = Number(process.env.FTP_PORT ?? '21');
const ftpSecure = (process.env.FTP_SECURE ?? 'true') === 'true';
const remoteBaseDir = process.env.REMOTE_BASE_DIR ?? '';
const publicAppUrl = process.env.PUBLIC_APP_URL ?? '';

// -- Sicherheitspruefungen (Abschnitt 26 der Spezifikation) --------------

function fail(message) {
  console.error(`Deployment abgebrochen: ${message}`);
  process.exit(1);
}

if (remoteBaseDir === '') fail('REMOTE_BASE_DIR ist leer.');
if (remoteBaseDir === '/') fail('REMOTE_BASE_DIR darf nicht "/" sein.');
if (remoteBaseDir === '/html') fail('REMOTE_BASE_DIR darf nicht "/html" sein.');
if (remoteBaseDir === '/html/apps') fail('REMOTE_BASE_DIR darf nicht "/html/apps" sein.');
if (!remoteBaseDir.endsWith('/familieninsel')) {
  fail('REMOTE_BASE_DIR muss auf "/familieninsel" enden.');
}
if (ftpPassword === '') fail('FTP_PASSWORD ist leer. Bitte in .env.deploy eintragen.');

const deployDir = join(root, 'deploy');
if (!existsSync(deployDir) || !existsSync(join(deployDir, 'index.html'))) {
  fail('Lokales deploy/-Verzeichnis fehlt oder ist unvollstaendig. Vorher "npm run build" ausfuehren.');
}
if (!existsSync(join(deployDir, 'api', 'index.php'))) {
  fail('deploy/api/index.php fehlt. Build scheint unvollstaendig zu sein.');
}

console.log(`Ziel: ${ftpHost}:${ftpPort}${remoteBaseDir} (FTPS: ${ftpSecure ? 'ja' : 'nein'})`);

const client = new Client();
client.ftp.verbose = false;

try {
  await client.access({
    host: ftpHost,
    user: ftpUser,
    password: ftpPassword,
    port: ftpPort,
    secure: ftpSecure,
  });

  // ensureDir wechselt zusaetzlich in das Zielverzeichnis und legt es bei Bedarf an.
  // storage/ wird bewusst nie von diesem Skript beruehrt - PHP legt es bei Bedarf selbst an.
  await client.ensureDir(remoteBaseDir);

  console.log('Lade Dateien hoch ...');
  await client.uploadFromDir(deployDir);

  console.log('\nDeployment abgeschlossen.');
  if (publicAppUrl !== '') {
    console.log(`Oeffentliche URL: ${publicAppUrl}`);
  }
} catch (error) {
  console.error('Deployment fehlgeschlagen:', error.message ?? error);
  process.exitCode = 1;
} finally {
  client.close();
}
