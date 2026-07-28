import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const deployDir = join(root, 'deploy');
const isWindows = process.platform === 'win32';

function run(command, args) {
  console.log(`\n> ${command} ${args.join(' ')}`);
  execFileSync(command, args, { stdio: 'inherit', shell: isWindows });
}

console.log('== 1/4: PHP-Syntaxcheck ==');
run('node', ['scripts/lint-php.mjs']);

console.log('\n== 2/4: Frontend-Build (Typecheck + Vite) ==');
run('npm', ['run', 'build', '--workspace=frontend']);

console.log('\n== 3/4: Deploy-Verzeichnis zusammenstellen ==');
rmSync(deployDir, { recursive: true, force: true });
mkdirSync(deployDir, { recursive: true });

// Frontend
cpSync(join(root, 'frontend', 'dist'), deployDir, { recursive: true });

// backend/ wird 1:1 wie lokal deployt (public/, src/, config/, database/),
// damit relative Pfade (z. B. "../vendor/autoload.php" in public/index.php,
// "../storage/..." in config.php) identisch zu lokal bleiben. Ein Deny-All
// .htaccess sperrt den direkten Web-Zugriff auf diesen Ordner.
const backendDeployDir = join(deployDir, 'backend');
mkdirSync(backendDeployDir, { recursive: true });
cpSync(join(root, 'backend', 'public'), join(backendDeployDir, 'public'), { recursive: true });
cpSync(join(root, 'backend', 'src'), join(backendDeployDir, 'src'), { recursive: true });
cpSync(join(root, 'backend', 'config'), join(backendDeployDir, 'config'), { recursive: true });
cpSync(join(root, 'backend', 'database'), join(backendDeployDir, 'database'), { recursive: true });
cpSync(join(root, 'backend', '.htaccess'), join(backendDeployDir, '.htaccess'));

// Produktive Composer-Abhaengigkeiten (nur fuer den Autoloader benoetigt, ohne PHPUnit etc.)
cpSync(join(root, 'backend', 'composer.json'), join(backendDeployDir, 'composer.json'));
if (existsSync(join(root, 'backend', 'composer.lock'))) {
  cpSync(join(root, 'backend', 'composer.lock'), join(backendDeployDir, 'composer.lock'));
}
run('composer', ['install', '--no-dev', '--optimize-autoloader', '--quiet', `--working-dir=${backendDeployDir}`]);
rmSync(join(backendDeployDir, 'composer.json'));
rmSync(join(backendDeployDir, 'composer.lock'), { force: true });

// Oeffentlicher API-Einstiegspunkt: duenner Proxy, der den "echten" Front-Controller
// unter backend/public/index.php einbindet. So bleibt dessen __DIR__-basierte
// Pfadauflösung (Autoloader, Config) unveraendert, egal ob lokal oder deployt.
const apiDir = join(deployDir, 'api');
mkdirSync(apiDir, { recursive: true });
writeFileSync(
  join(apiDir, 'index.php'),
  `<?php

declare(strict_types=1);

require __DIR__ . '/../backend/public/index.php';
`,
);
cpSync(join(root, 'backend', 'public', '.htaccess'), join(apiDir, '.htaccess'));

// Root .htaccess: SPA-Fallback, aber /api und /backend nicht auf index.html umleiten.
writeFileSync(
  join(deployDir, '.htaccess'),
  `RewriteEngine On

RewriteRule ^api/ - [L]
RewriteRule ^backend/ - [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [L]
`,
);

console.log('\n== 4/4: Fertig ==');
console.log(`Deploy-Verzeichnis erstellt unter: ${deployDir}`);
console.log('Hinweis: backend/storage/ wird bewusst nicht lokal erzeugt und beim Deploy nicht angefasst.');
