import { execFileSync } from 'node:child_process';
import { walkFiles } from './lib/walk.mjs';

const targets = ['backend/src', 'backend/public'];
const files = targets.flatMap((dir) => walkFiles(dir, '.php'));

console.log(`Pruefe ${files.length} PHP-Dateien mit "php -l" ...`);

let hasErrors = false;

for (const file of files) {
  try {
    execFileSync('php', ['-l', file], { stdio: 'pipe' });
  } catch (error) {
    hasErrors = true;
    console.error(error.stdout?.toString() ?? error.message);
  }
}

if (hasErrors) {
  console.error('PHP-Syntaxfehler gefunden.');
  process.exit(1);
}

console.log('Keine PHP-Syntaxfehler gefunden.');
