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
│   ├── Controllers/   Auth, Players, Tasks, Resources, Buildings, Minigames, Activity, Health
│   ├── Services/      AuthService, TaskService, BuildingService, MinigameService (Geschaeftslogik, keine SQL-Statements)
│   ├── Repositories/  Family/Player/Task/Resource/ResourceTransaction/ActivityLog/Building/FamilyBuilding/Minigame-Repository (einziger Ort mit SQL)
│   ├── Middleware/     Cors, RequireFamilySession, RequireAuth, RequireParent, Csrf
│   ├── Database/       Connection (PDO-Factory), Migrator (Auto-Migrate), Seeder (Demo-Familie, Ressourcen, Demo-Aufgaben, Strandhuette, Schatzsuche)
│   ├── Support/        JsonResponse, Router (mit {param}-Matching), Session, Request, Logger, Clock
│   └── Game/            (noch leer – die Schatzsuche-Logik lebt komplett im Frontend, siehe unten)
├── config/            Zentrale Konfiguration (CORS-Origins, DB-Pfad, Session/Security-Werte)
├── database/           migrations/ (SQL, auto-angewendet), seeds/ (bisher ungenutzt, Seeding laeuft ueber Seeder.php)
├── storage/            database/, logs/, backups/ – nie versioniert, nie deployt überschrieben
└── tests/              PHPUnit (58 Tests: Connection, Router, JsonResponse, Migrator, Seeder, AuthService, Session/Middleware, TaskService, BuildingService, MinigameService)
```

**Namenskonvention bewusst beachtet:** Ordner unter `src/` sind exakt so großgeschrieben wie die PSR-4-Namespace-Segmente (`Controllers`, `Services`, …). Grund: Beim Schwesterprojekt `neighborhood` hat ein Autoloader, der Namespace-Segmente klein schrieb, obwohl die Ordner auf der Platte großgeschrieben waren, auf dem case-insensitiven Windows-Dev-Rechner nie ein Problem gezeigt – auf dem case-sensitiven Linux-Produktivserver aber jeden Request mit 500 quittiert. Hier gibt es diese Diskrepanz gar nicht erst: `composer.json` mappt `App\` 1:1 auf `src/`, ohne Case-Transformation.

## Routing

`backend/src/Support/Router.php` unterstuetzt seit Phase 2 einfaches `{param}`-Matching (ein Platzhalter pro Segment, keine Regex-Constraints). Wurde bewusst erst eingebaut, als die ersten Routen mit IDs (`/tasks/{id}/...`) tatsaechlich gebraucht wurden, nicht vorab.

## Authentifizierung & Sessions (Phase 1)

Zweistufiges Session-Modell in `App\Support\Session`:

1. **Familien-Session** (`family-login`): Nach korrektem Familiencode wird nur `family_id` gesetzt und die Session-ID regeneriert (Session-Fixation-Schutz). Damit ist `GET /api/players` bereits nutzbar (Profilauswahl braucht die Liste, bevor ein Profil gewaehlt ist).
2. **Profil-Session** (`select-profile`): Setzt zusaetzlich `player_id` und `role`. Ab hier greift `RequireAuth`.
3. **Eltern-Freischaltung** (`parent-unlock`): Nur relevant fuer `role = parent`. Setzt einen Zeitstempel (`parent_unlocked_until`, Default 15 Minuten), geprueft von `RequireParent`. Ein Kind-Profil braucht das nie.

CSRF: `GET /auth/session` liefert immer ein (bei Bedarf neu erzeugtes) `csrfToken` im Body. Das Frontend haengt es bei jedem schreibenden Request als `X-CSRF-Token`-Header an (`services/api.ts`). `App\Middleware\Csrf` prueft das bei allen Nicht-GET-Requests.

Session-Cookie: kein explizites `Domain`-Attribut (funktioniert dadurch sowohl in Produktion als auch ueber den lokalen Vite-Proxy, siehe unten), `SameSite=Lax`, `HttpOnly`, `Secure` nur wenn die Anfrage tatsaechlich per HTTPS reinkam.

**PIN-Sperre ist session-gebunden, nicht global**: `Session::registerFailedPinAttempt()` zaehlt Fehlversuche pro Browser-Session (Default: 5 Versuche, 60s Sperre). Ein Angreifer koennte das durch Loeschen der Cookies umgehen. Fuer die Bedrohungslage dieser privaten Familien-App (keine oeffentliche Erreichbarkeit im eigentlichen Sinn, Ziel ist "neugieriges Kind rät nicht versehentlich die PIN") ausreichend; fuer eine haertere Garantie muesste die Sperre serverseitig pro Player-ID in der DB gefuehrt werden.

## Bausystem (Phase 3)

MVP-Annahme: **hoechstens ein Bauprojekt pro Familie insgesamt** (nicht nur "gleichzeitig aktiv") – `FamilyBuildingRepository::findForFamily()` liefert schlicht die neueste Zeile. Sobald spaeter mehrere Gebaeude/Bauauswahl noetig sind, muss das um eine echte Auswahl-/Start-Logik erweitert werden (aktuell startet die Strandhuette automatisch beim ersten Request ueber `Seeder::seedActiveFamilyBuildingIfEmpty()`).

**Baustufen-Berechnung** (`BuildingService::calculateStage()`): rein prozentual auf Basis der Gesamtsumme aller Rohstoffe (nicht pro Rohstoffart), 5 Stufen: 0 % = Bauplatz, 1–33 % = Fundament, 34–66 % = Waende, 67–99 % = Dach, 100 % = fertig. Einfache, bewusst grobe Heuristik statt einer Konfigurationstabelle pro Baustufe – ausreichend fuer ein einzelnes Gebaeude mit vier Rohstoffarten.

**Einzahlung wird auf den Restbedarf gedeckelt**: Zahlt ein Elternteil mehr von einem Rohstoff ein, als das Gebaeude noch braucht (z. B. 100 Holz bei nur 12 fehlenden), zieht der Server nur die tatsaechlich benoetigte Menge ab (`min(angefragt, restbedarf)`). Verhindert versehentliche Verschwendung, ohne dass das Frontend selbst rechnen muesste; das "Alle verfuegbaren Rohstoffe einsetzen" im Frontend nutzt das aus, indem es einfach den vollen Kontostand vorschlaegt.

Fertigstellung ist wie bei Aufgaben-Belohnungen doppelt abgesichert: `FamilyBuildingRepository::markCompleted()` aktualisiert nur, wenn `status = 'in_progress'` (per `rowCount()` geprueft) – ein zweiter Abschluss-Versuch (oder ein Race) kann daher nie zweimal den "Gebaeude fertiggestellt"-Tagebucheintrag erzeugen.

**Warum die Rollenpruefung noch keinen echten Business-Endpunkt schuetzt:** `RequireParent` ist fertig und per PHPUnit getestet (`SessionMiddlewareTest`), wird aber in Phase 1 auf keinen Endpunkt "scharf geschaltet", weil es in dieser Phase noch keine Eltern-only-Aktion gibt (Aufgaben erstellen/bestaetigen kommt erst in Phase 2). Die vollstaendige End-to-End-Demonstration "Kind kann Eltern-Aktion nicht ausfuehren" entsteht automatisch, sobald Phase 2 `POST /api/tasks` hinter `RequireParent` haengt.

## Minispiele (Phase 4)

**Schatzsuche lebt komplett im Frontend** (`features/minigames/TreasureHuntGame.tsx`): 5 fest positionierte "Sandhaufen"-Buttons, die beim Klick das eigentliche Objekt (Emoji + Name) aufdecken. Es gibt keine serverseitige Spiellogik – der Server bekommt nur "abgeschlossen" gemeldet (`POST /api/minigames/{key}/complete`) und kuemmert sich ausschliesslich um Freischaltung, einmalige Belohnung und Wiederholbarkeit. Deshalb bleibt `backend/src/Game/` leer; das ist bewusst so und kein vergessener Ordner.

**Freischaltung ist an Gebaeude gekoppelt, nicht direkt an Aktionen**: `buildings.unlock_minigame_key` zeigt auf `minigames.key`. Wird ein Gebaeude fertig (`BuildingService::contribute()`, `justCompleted === true`), schaltet der gleiche Transaktions-Block automatisch das verknuepfte Minispiel frei (`MinigameRepository::unlockForFamily()`, idempotent per `INSERT OR IGNORE` + `UNIQUE(family_id, minigame_id)`). Kommen spaeter weitere Gebaeude mit eigenen Minispielen dazu, muss an dieser Stelle nichts geaendert werden.

**Schutz vor Farmen**: `family_minigames.first_completion_at` wird nur beim allerersten Abschluss gesetzt (`MinigameRepository::markFirstCompletion()`, per `rowCount()` erkannt – exakt das gleiche Muster wie bei Aufgaben- und Gebaeude-Fertigstellung). Jede weitere Runde liefert `starsAwarded: 0` zurueck; das Spiel selbst bleibt beliebig oft spielbar, es gibt nur keine zusaetzliche Belohnung.

### Set-Cookie und Secure-Flag im Dev-Proxy

Die Live-API setzt Session-Cookies mit `Secure` (sie laeuft produktiv immer unter HTTPS). Der lokale Vite-Dev-Server liefert die Seite aber ueber `http://localhost` aus – ein `Secure`-Cookie wuerde der Browser dort stillschweigend verwerfen, Sessions wuerden nie persistieren. `vite.config.ts` entfernt deshalb im `proxyRes`-Hook gezielt nur das `Secure`-Attribut aus dem `Set-Cookie`-Header (HttpOnly/SameSite bleiben erhalten). Das betrifft ausschliesslich den lokalen Dev-Betrieb; der Produktions-Build durchlaeuft diesen Code-Pfad nie.

## API-Antwortformat

Einheitlich über `App\Support\JsonResponse::success()` / `::error()`, siehe `docs/product-spec.md` Abschnitt 15. Niemals Stacktraces oder interne Pfade nach außen geben.

## Kritische Einschraenkung: SQLite-Version auf dem Produktivserver

Der Produktivserver (Alfahosting) liefert **SQLite 3.7.17 (2013)** aus – bestaetigt per Diagnose-Skript, PHP selbst ist 8.3.31. Das lokale Entwicklungssystem hat eine deutlich neuere SQLite-Version, wodurch ein Kompatibilitaetsproblem beim ersten Live-Test von Phase 2 lokal unsichtbar blieb und erst live als 500-Fehler auffiel.

**Konkret:** `INSERT ... ON CONFLICT (...) DO UPDATE ...` (SQLite-"Upsert", erst ab 3.24.0 verfuegbar) wurde in `ResourceRepository::incrementBalance()` verwendet und brach auf dem Server mit `SQLSTATE[HY000]: General error: 1 near "ON": syntax error` ab, sobald eine Aufgabe bestaetigt wurde. Fix: zwei einfache Anweisungen (`INSERT OR IGNORE` gefolgt von `UPDATE ... SET amount = amount + ...`) statt Upsert-Syntax – funktioniert auf jeder SQLite-Version.

**Regel fuer alle folgenden Phasen:** Keine SQLite-Funktionen verwenden, die neuer als etwa Version 3.8 sind (kein Upsert, keine Window-Functions, kein `RETURNING`, keine generierten Spalten, keine `STRICT`-Tables). Im Zweifel: einfache, klassische SQL-Anweisungen bevorzugen und nach jeder Aenderung an dieser Stelle **live** (nicht nur lokal) testen, da lokale Tests dieses Problem nicht aufdecken.

## Frontend-Struktur

```text
frontend/src/
├── app/           App.tsx (Router-Setup)
├── pages/         HomePage (Dashboard: Ressourcen, Kind-/Eltern-Aufgabenansicht)
├── hooks/         useTasksAndResources (gemeinsames Fetch/Refresh fuer Aufgaben + Ressourcen)
├── features/      auth/ (Phase 1), tasks/ (Phase 2: TaskCard, ChildTaskList, ParentTaskDashboard, CreateTaskForm),
│                  resources/ (Phase 2: ResourceBar)
│                  family/, buildings/, island/, minigames/ folgen je Phase
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
