# Familien-Insel

Kooperatives Familienspiel: reale Aufgaben verdienen Rohstoffe, die Familie baut gemeinsam ihre Insel aus.

Vollständige Spezifikation: [`docs/product-spec.md`](docs/product-spec.md). Architektur: [`docs/architecture.md`](docs/architecture.md).

## Voraussetzungen

- Node.js 22+ / npm 10+
- PHP 8.1+ mit aktivierten Extensions `pdo_sqlite` und `sqlite3`
- Composer 2.x

## Installation

```bash
npm install
cd backend && composer install && cd ..
```

## Entwicklung

```bash
npm run dev
```

Startet ausschließlich den Vite-Dev-Server (Port 5173, weicht bei Belegung automatisch aus). **Es gibt keinen lokalen PHP-Server** – Vite proxyt `/apps/familieninsel/api` serverseitig an die live deployte API weiter (siehe `vite.config.ts`, `server.proxy`). Der Browser sieht dabei nur `localhost` (kein CORS nötig), die Daten kommen trotzdem live vom echten Backend. Backend-Änderungen werden erst nach einem Deployment sichtbar – Details und Hintergrund siehe [`docs/architecture.md`](docs/architecture.md#entwicklungsmodell-kein-lokaler-php-server).

## Tests

```bash
npm test                 # Frontend (Vitest) + Backend (PHPUnit)
npm run test --workspace=frontend
php backend/vendor/bin/phpunit -c backend/phpunit.xml
```

## Build

```bash
npm run build
```

Prüft PHP-Syntax, baut das Frontend (Typecheck + Vite) und stellt ein deploybares Verzeichnis unter `deploy/` zusammen (nicht versioniert).

## Deployment

```bash
cp .env.deploy.example .env.deploy
# FTP_PASSWORD in .env.deploy eintragen
npm run deploy
```

Lädt ausschließlich nach `/html/apps/familieninsel` auf `www.red-it.org` hoch (Alfahosting, FTPS). Persistente Daten (`backend/storage/`) werden dabei nie überschrieben. `.env.deploy` ist gitignored und enthält Zugangsdaten – niemals committen.

Ausführliche Anleitung für Neuinstallation, Datenbank-Backup/Restore und Sicherheitscheck: [`docs/deployment.md`](docs/deployment.md).

## Projektstruktur

```text
frontend/   Vite + React + TypeScript
backend/    PHP 8.x JSON-API (PDO/SQLite)
scripts/    Build- und Deploy-Automatisierung
docs/       Produktspezifikation, Architektur, Referenzgrafiken
```
