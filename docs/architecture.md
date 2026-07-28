# Architektur – Familien-Insel

## Überblick

```text
familieninsel/
├── frontend/        Vite + React + TypeScript SPA
├── backend/         PHP 8.x JSON-API (PDO/SQLite, keine Framework-Abhängigkeit)
├── scripts/         Build- und Deploy-Automatisierung (Node, keine Zusatztools)
├── docs/            Diese Dokumentation + Referenzgrafiken
└── deploy/          Generiertes, nicht versioniertes Deploy-Artefakt
```

## Entwicklungsmodell: kein lokaler PHP-Server

Bewusste Entscheidung (siehe Projekt-Feedback): Es gibt **keinen** lokalen PHP-Entwicklungsserver. Stattdessen läuft nur der Vite-Dev-Server lokal, und `vite.config.ts` proxyt alle Aufrufe unter `/apps/familieninsel/api` serverseitig (Node-Prozess, nicht der Browser) an `https://www.red-it.org` weiter. Der Browser sieht dadurch ausschließlich `localhost` – die Daten kommen trotzdem live vom echten Backend.

**Warum ein Proxy statt direktem Cross-Origin-Fetch:** Der erste Versuch war ein direkter Browser-Fetch von `localhost` gegen `https://www.red-it.org/apps/familieninsel/api`. Dabei wurde entdeckt, dass die Hosting-Plattform (vermutlich ein domainweiter Plesk/nginx-Security-Header-Preset, unabhängig von dieser App) bei jeder Antwort zusätzlich einen eigenen `Access-Control-Allow-Origin: *`-Header injiziert – **zusätzlich** zu dem korrekten, originspezifischen Header aus `App\Middleware\Cors`. Zwei `Access-Control-Allow-Origin`-Header in einer Antwort sind laut Fetch-Spezifikation ungültig; Browser verwerfen solche Antworten komplett (reproduziert: `Failed to fetch` im echten Browser-Test). Per FTP lässt sich das nicht beheben (keine Plesk-Panel-Zugriff auf Nginx-Konfiguration). Der serverseitige Vite-Proxy umgeht das Problem elegant, weil der Browser dabei nie eine Cross-Origin-Anfrage stellt – CORS-Header spielen dann gar keine Rolle mehr. In Produktion (Frontend und API unter derselben Origin `www.red-it.org`) besteht das Problem ohnehin nicht, da Browser CORS nur bei Cross-Origin-Requests prüfen.

**Konsequenzen, die das Modell weiterhin mit sich bringt:**

1. **Jede Backend-Änderung erfordert ein Deployment**, um lokal sichtbar zu werden. PHP-Unit-Tests laufen weiterhin rein lokal (PHPUnit braucht keinen laufenden Server), aber das manuelle Durchklicken im Browser passiert immer gegen die Live-API (via Proxy).
2. **Keine Staging-Umgebung.** Lokale Entwicklung schreibt in dieselbe SQLite-Datenbank, die die Familie produktiv nutzt. Das ist für ein privates Familienprojekt im MVP-Stadium akzeptiert, aber ein bewusstes Risiko: ein Bug in einer neuen Phase kann echten Spielfortschritt beschädigen. Mildernd: `scripts/deploy.mjs` fasst `storage/` nie an, und `backend/storage/backups/` ist für manuelle Sicherungen vorgesehen (noch nicht automatisiert – siehe offene Punkte in Phase 6).
3. **Bekannte, ungeloeste Server-Eigenheit:** Die doppelten `Access-Control-Allow-Origin`-Header bestehen auf Serverebene weiter (nur durch den Proxy umgangen, nicht behoben). Falls die App später von einem anderen Origin aus angesprochen werden soll (z. B. eine native App oder ein zweites Tool), müsste das im Plesk-Panel der Domain untersucht werden (Stichwort: domainweite Security-Header/OWASP-Preset).

## Backend-Struktur

```text
backend/
├── public/           Web-Root des API-Einstiegspunkts (index.php, .htaccess)
├── src/
│   ├── Controllers/   Nimmt Request entgegen, ruft Services, formt JsonResponse
│   ├── Services/       (noch leer – Geschäftslogik ab Phase 1)
│   ├── Repositories/   (noch leer – Datenzugriff ab Phase 1)
│   ├── Middleware/     Cors (weitere Middleware, z. B. Auth, folgt in Phase 1)
│   ├── Database/       PDO-Connection-Factory
│   ├── Support/        JsonResponse, Router (schlanke Eigenbauten, kein Framework)
│   └── Game/            (noch leer – Spiellogik ab Phase 3/4)
├── config/            Zentrale Konfiguration (CORS-Origins, DB-Pfad, Zeitzone)
├── database/           migrations/, seeds/ (folgen ab Phase 1)
├── storage/            database/, logs/, backups/ – nie versioniert, nie deployt überschrieben
└── tests/              PHPUnit
```

**Namenskonvention bewusst beachtet:** Ordner unter `src/` sind exakt so großgeschrieben wie die PSR-4-Namespace-Segmente (`Controllers`, `Services`, …). Grund: Beim Schwesterprojekt `neighborhood` hat ein Autoloader, der Namespace-Segmente klein schrieb, obwohl die Ordner auf der Platte großgeschrieben waren, auf dem case-insensitiven Windows-Dev-Rechner nie ein Problem gezeigt – auf dem case-sensitiven Linux-Produktivserver aber jeden Request mit 500 quittiert. Hier gibt es diese Diskrepanz gar nicht erst: `composer.json` mappt `App\` 1:1 auf `src/`, ohne Case-Transformation.

## Routing

`backend/src/Support/Router.php` ist bewusst minimal (exakter Pfad-Match, kein Regex/Parameter-Matching). Für Phase 0 reicht das (`/health`). Sobald Phase 1 Routen mit IDs braucht (`/api/tasks/{id}`), muss der Router um Parameter-Matching erweitert werden – das wurde nicht vorab gebaut, um keine ungenutzte Komplexität einzuführen.

## API-Antwortformat

Einheitlich über `App\Support\JsonResponse::success()` / `::error()`, siehe `docs/product-spec.md` Abschnitt 15. Niemals Stacktraces oder interne Pfade nach außen geben.

## Frontend-Struktur

```text
frontend/src/
├── app/           App.tsx (Router-Setup)
├── pages/         Routbare Seiten
├── features/      auth/, family/, tasks/, resources/, buildings/, island/, minigames/
│                  (Ordner angelegt, Inhalt folgt je Phase)
├── components/    Wiederverwendbare, feature-übergreifende UI-Bausteine
├── services/      Zentrale API-Abstraktion (api.ts) + feature-spezifische Services
├── hooks/         Zustandslogik, sobald benötigt
├── types/         Zentrale Typdefinitionen (u. a. API-Response-Typen)
└── styles/        Globale Styles
```

`vite.config.ts` setzt `base` nur im Build (`/apps/familieninsel/`), im Dev-Server bleibt `/`. `App.tsx` liest den `basename` für React Router aus `import.meta.env.BASE_URL`, sodass beide Modi automatisch korrekt sind.

## Build- und Deploy-Pipeline

- `npm run build` → `scripts/build.mjs`: PHP-Lint → Frontend-Build (`tsc -b && vite build`) → baut `deploy/` zusammen (Frontend-Dateien im Root, `api/` mit Front-Controller, `backend/` mit `src/config/database` + produktiv installiertem `vendor/` via `composer install --no-dev`).
- `npm run deploy` → `scripts/deploy.mjs`: lädt `.env.deploy`, prüft `REMOTE_BASE_DIR` gegen eine Deny-Liste (leer, `/`, `/html`, `/html/apps` verboten; muss auf `/familieninsel` enden), prüft `FTP_PASSWORD` nicht leer und `deploy/` vorhanden, verbindet per FTPS und lädt ausschließlich nach `REMOTE_BASE_DIR` hoch. `storage/` wird nie lokal erzeugt und nie per Deploy anfasst – die Anwendung legt `storage/database/` beim ersten Health-Check-Aufruf selbst an (`App\Database\Connection::make()`).
- Bekannte Grenze: Der Upload überschreibt/ergänzt Dateien, löscht aber keine alten, nicht mehr referenzierten Frontend-Assets (z. B. Vite-Hash-Bundles vorheriger Builds) auf dem Server. Für das MVP unkritisch, ggf. später durch einen expliziten Abgleich lösen.

## Lokale Umgebungs-Voraussetzungen (dieser Rechner)

- PHP 8.3 CLI, `pdo_sqlite`/`sqlite3`-Extensions wurden in der lokalen `php.ini` aktiviert (waren installiert, aber auskommentiert).
- Composer 2.9 vorhanden, wird für `backend/vendor` (PHPUnit lokal, produktiver Autoloader beim Build) genutzt.
- Node 22 / npm 10, Root ist ein npm-Workspace (`frontend` als Workspace-Package).
