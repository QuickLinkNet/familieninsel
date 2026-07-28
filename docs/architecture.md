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
│   ├── Controllers/   Auth, Players, Health – nimmt Request entgegen, ruft Services, formt JsonResponse
│   ├── Services/      AuthService (Familiencode/PIN/Profile, keine SQL-Statements)
│   ├── Repositories/  FamilyRepository, PlayerRepository (einziger Ort mit SQL)
│   ├── Middleware/     Cors, RequireFamilySession, RequireAuth, RequireParent, Csrf
│   ├── Database/       Connection (PDO-Factory), Migrator (Auto-Migrate), Seeder (Demo-Familie)
│   ├── Support/        JsonResponse, Router, Session, Request, Logger
│   └── Game/            (noch leer – Spiellogik ab Phase 3/4)
├── config/            Zentrale Konfiguration (CORS-Origins, DB-Pfad, Session/Security-Werte)
├── database/           migrations/ (SQL, auto-angewendet), seeds/ (bisher ungenutzt, Seeding laeuft ueber Seeder.php)
├── storage/            database/, logs/, backups/ – nie versioniert, nie deployt überschrieben
└── tests/              PHPUnit (26 Tests: Connection, Router, JsonResponse, Migrator, Seeder, AuthService, Session/Middleware)
```

**Namenskonvention bewusst beachtet:** Ordner unter `src/` sind exakt so großgeschrieben wie die PSR-4-Namespace-Segmente (`Controllers`, `Services`, …). Grund: Beim Schwesterprojekt `neighborhood` hat ein Autoloader, der Namespace-Segmente klein schrieb, obwohl die Ordner auf der Platte großgeschrieben waren, auf dem case-insensitiven Windows-Dev-Rechner nie ein Problem gezeigt – auf dem case-sensitiven Linux-Produktivserver aber jeden Request mit 500 quittiert. Hier gibt es diese Diskrepanz gar nicht erst: `composer.json` mappt `App\` 1:1 auf `src/`, ohne Case-Transformation.

## Routing

`backend/src/Support/Router.php` ist bewusst minimal (exakter Pfad-Match, kein Regex/Parameter-Matching). Alle Phase-1-Routen (`/health`, `/auth/*`, `/players`) kommen ohne Pfad-Parameter aus. Sobald Phase 2 Routen mit IDs braucht (`/api/tasks/{id}`), muss der Router um Parameter-Matching erweitert werden – das wurde nicht vorab gebaut, um keine ungenutzte Komplexität einzuführen.

## Authentifizierung & Sessions (Phase 1)

Zweistufiges Session-Modell in `App\Support\Session`:

1. **Familien-Session** (`family-login`): Nach korrektem Familiencode wird nur `family_id` gesetzt und die Session-ID regeneriert (Session-Fixation-Schutz). Damit ist `GET /api/players` bereits nutzbar (Profilauswahl braucht die Liste, bevor ein Profil gewaehlt ist).
2. **Profil-Session** (`select-profile`): Setzt zusaetzlich `player_id` und `role`. Ab hier greift `RequireAuth`.
3. **Eltern-Freischaltung** (`parent-unlock`): Nur relevant fuer `role = parent`. Setzt einen Zeitstempel (`parent_unlocked_until`, Default 15 Minuten), geprueft von `RequireParent`. Ein Kind-Profil braucht das nie.

CSRF: `GET /auth/session` liefert immer ein (bei Bedarf neu erzeugtes) `csrfToken` im Body. Das Frontend haengt es bei jedem schreibenden Request als `X-CSRF-Token`-Header an (`services/api.ts`). `App\Middleware\Csrf` prueft das bei allen Nicht-GET-Requests.

Session-Cookie: kein explizites `Domain`-Attribut (funktioniert dadurch sowohl in Produktion als auch ueber den lokalen Vite-Proxy, siehe unten), `SameSite=Lax`, `HttpOnly`, `Secure` nur wenn die Anfrage tatsaechlich per HTTPS reinkam.

**PIN-Sperre ist session-gebunden, nicht global**: `Session::registerFailedPinAttempt()` zaehlt Fehlversuche pro Browser-Session (Default: 5 Versuche, 60s Sperre). Ein Angreifer koennte das durch Loeschen der Cookies umgehen. Fuer die Bedrohungslage dieser privaten Familien-App (keine oeffentliche Erreichbarkeit im eigentlichen Sinn, Ziel ist "neugieriges Kind rät nicht versehentlich die PIN") ausreichend; fuer eine haertere Garantie muesste die Sperre serverseitig pro Player-ID in der DB gefuehrt werden.

**Warum die Rollenpruefung noch keinen echten Business-Endpunkt schuetzt:** `RequireParent` ist fertig und per PHPUnit getestet (`SessionMiddlewareTest`), wird aber in Phase 1 auf keinen Endpunkt "scharf geschaltet", weil es in dieser Phase noch keine Eltern-only-Aktion gibt (Aufgaben erstellen/bestaetigen kommt erst in Phase 2). Die vollstaendige End-to-End-Demonstration "Kind kann Eltern-Aktion nicht ausfuehren" entsteht automatisch, sobald Phase 2 `POST /api/tasks` hinter `RequireParent` haengt.

### Set-Cookie und Secure-Flag im Dev-Proxy

Die Live-API setzt Session-Cookies mit `Secure` (sie laeuft produktiv immer unter HTTPS). Der lokale Vite-Dev-Server liefert die Seite aber ueber `http://localhost` aus – ein `Secure`-Cookie wuerde der Browser dort stillschweigend verwerfen, Sessions wuerden nie persistieren. `vite.config.ts` entfernt deshalb im `proxyRes`-Hook gezielt nur das `Secure`-Attribut aus dem `Set-Cookie`-Header (HttpOnly/SameSite bleiben erhalten). Das betrifft ausschliesslich den lokalen Dev-Betrieb; der Produktions-Build durchlaeuft diesen Code-Pfad nie.

## API-Antwortformat

Einheitlich über `App\Support\JsonResponse::success()` / `::error()`, siehe `docs/product-spec.md` Abschnitt 15. Niemals Stacktraces oder interne Pfade nach außen geben.

## Frontend-Struktur

```text
frontend/src/
├── app/           App.tsx (Router-Setup)
├── pages/         Routbare Seiten
├── features/      auth/ (AuthContext, AuthGate, Login/Profil/PIN-Screens – Phase 1)
│                  family/, tasks/, resources/, buildings/, island/, minigames/ folgen je Phase
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
