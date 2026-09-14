# Deployment – Familien-Insel

Ziel-Setup: Alfahosting (Plesk), Domain `www.red-it.org`, Unterordner `/apps/familieninsel/`. Details zur technischen Umsetzung (Build-Pipeline, Proxy, SQLite-Einschränkungen) stehen in [`architecture.md`](architecture.md) – dieses Dokument ist die praktische Anleitung für Installation, Backup und Sicherheitscheck.

## Voraussetzungen auf dem Zielserver

- PHP 8.1+ mit aktivierten Extensions `pdo_sqlite` und `sqlite3`
- Schreibrechte für den PHP-Prozess innerhalb von `/html/apps/familieninsel/backend/` (die Anwendung legt `storage/database/familieninsel.sqlite` beim ersten Request selbst an)
- FTP- oder FTPS-Zugang für den Upload

Lokal zum Bauen/Deployen: Node.js 22+, PHP 8.1+, Composer 2.x (siehe [`README.md`](../README.md) für die genauen Befehle).

## Neuinstallation von Grund auf

Für den Fall, dass die Anwendung auf einem neuen Server oder nach einem Totalverlust neu aufgesetzt werden muss:

1. Repository klonen, `npm install`, `cd backend && composer install && cd ..`.
2. `.env.deploy.example` nach `.env.deploy` kopieren, `FTP_PASSWORD` eintragen. `REMOTE_BASE_DIR` muss exakt auf den Zielordner zeigen (siehe Warnhinweis unten zum FTP-Pfad).
3. `npm run build` – prüft PHP-Syntax, baut das Frontend, stellt `deploy/` zusammen (inkl. produktivem Composer-Autoloader, ohne Dev-Abhängigkeiten wie PHPUnit).
4. `npm run deploy` – lädt ausschließlich nach `REMOTE_BASE_DIR` hoch. Bricht kontrolliert ab, wenn der Pfad verdächtig aussieht (leer, `/`, `/html`, `/html/apps`) oder das Passwort fehlt.
5. Ersten Request an `https://<domain>/apps/familieninsel/api/health` schicken – das migriert die Datenbank automatisch und legt die Demo-Familie, Rohstoffe, Demo-Aufgaben, die Strandhütte und die Schatzsuche an (`Seeder`-Klassen, siehe `backend/src/Database/Seeder.php`). Keine manuelle Installationsroutine nötig.
6. Eltern-PINs **umgehend ändern**, sobald die Anwendung produktiv für die echte Familie genutzt wird (Seeder legt vordefinierte Demo-PINs an, siehe `backend/src/Database/Seeder.php::PARENT_PINS`). Dafür gibt es einen Verwaltungs-Screen: als Elternteil einloggen, `/familie` öffnen, "Neue PIN setzen" bei sich selbst (oder dem anderen Elternteil) klicken - keine manuelle Datenbankänderung nötig.

### Bekannter FTP-Pfad-Fallstrick (Alfahosting)

Der FTP-Login-Root liegt bei diesem Hoster eine Ebene **über** dem echten Docroot – der reale Ordner heißt `html/`, darunter liegt `apps/`. `REMOTE_BASE_DIR` muss deshalb `/html/apps/familieninsel` lauten, nicht `/apps/familieninsel`. Bei einem anderen Hoster unbedingt zuerst die tatsächliche FTP-Verzeichnisstruktur prüfen (z. B. mit einem FTP-Client), bevor `.env.deploy` befüllt wird.

### Bekannter Fallstrick: FTPS scheitert mit "425 Unable to build data connection"

`FTP_SECURE=true` (FTPS) kann auf manchen Netzwerken zuverlässig mit `425 Unable to build data connection` fehlschlagen, obwohl Host/Zugangsdaten korrekt sind. Ursache meist eine Router-/Firewall-"FTP-ALG" (Application Layer Gateway), die bei **unverschlüsseltem** FTP die PASV-Antwort automatisch fürs NAT umschreibt, das bei **verschlüsseltem** FTPS aber nicht mehr lesen kann und die Datenverbindung blockiert. Betrifft dann typischerweise nur Rechner/Netzwerke mit einer solchen ALG – andere Projekte auf demselben Hoster mit `secure: false` sind davon nicht betroffen. Abhilfe: `FTP_SECURE=false` in `.env.deploy` setzen (Hoster erlaubt laut oben ausdrücklich "FTP- oder FTPS-Zugang").

## Backup und Wiederherstellung

Der komplette Spielstand einer Familie steckt in **einer einzigen Datei**: `backend/storage/database/familieninsel.sqlite` auf dem Server. Das Deploy-Skript fasst `storage/` absichtlich nie an – Backups und Wiederherstellung passieren manuell:

**Backup:**
1. Mit einem FTP-Client (z. B. FileZilla) oder dem Plesk-Dateimanager verbinden.
2. Nach `/html/apps/familieninsel/backend/storage/database/familieninsel.sqlite` navigieren.
3. Datei herunterladen und mit Datum im Dateinamen sichern (z. B. `familieninsel-2026-07-28.sqlite`).
4. Empfehlung: vor jedem `npm run deploy` mit Datenbank-relevanten Änderungen (neue Migration) sowie in regelmäßigen Abständen (z. B. wöchentlich), solange keine Automatisierung existiert.

**Wiederherstellung:**
1. Gesicherte `.sqlite`-Datei per FTP wieder nach `backend/storage/database/familieninsel.sqlite` hochladen (Dateiname exakt beibehalten).
2. Fertig – kein Neustart oder Migrationslauf nötig, die Anwendung liest die Datei beim nächsten Request.

**Nicht tun:** `backend/storage/` durch ein erneutes `npm run deploy` "reparieren" wollen – das Verzeichnis wird nie hochgeladen oder überschrieben, ein Deploy kann also weder ein Backup ersetzen noch eines beschädigen.

Die Ordner `backend/storage/logs/` (Sicherheitsereignisse wie fehlgeschlagene PIN-Versuche) und `backend/storage/backups/` (aktuell ungenutzt, für künftige automatisierte Backups vorgesehen) folgen demselben Prinzip.

## Sicherheitscheck (Stand Phase 6)

| Bereich | Umsetzung |
|---|---|
| SQL-Injection | Ausschließlich Prepared Statements (PDO), keine String-Konkatenation in Queries |
| CSRF | `X-CSRF-Token`-Header auf allen schreibenden Requests, serverseitig geprüft (`App\Middleware\Csrf`) |
| Sessions | `HttpOnly`, `SameSite=Lax`, `Secure` bei HTTPS, Session-Regeneration nach Login, konfigurierbares Idle-Timeout |
| Zugangsdaten | Eltern-PINs und Kind-Login-Tokens nur gehasht gespeichert (`password_hash`/`password_verify` bzw. SHA-256), nie im Klartext |
| Login-Bruteforce | Fehlversuche bei der Eltern-PIN gezählt und protokolliert, temporäre Sperre nach 5 Versuchen (`backend/storage/logs/security.log`) |
| Rollenprüfung | Serverseitig über `RequireAuth`/`RequireParent`, nie nur im Frontend |
| SQLite-Datei | Nicht öffentlich erreichbar (`backend/.htaccess` mit `Require all denied`, verifiziert per direktem Aufruf → 403) |
| Secrets | `.env.deploy` gitignored, keine Zugangsdaten im Repository (geprüft: `git ls-files` enthält keine `.env.deploy`) |
| XSS | React escaped standardmäßig, kein `dangerouslySetInnerHTML` im gesamten Frontend |
| Eingabelängen | Titel (120), Beschreibung (2000), Notiz (500), Eltern-PIN (genau 4 Ziffern), Login-Token (128) serverseitig begrenzt |
| Fehlerausgaben | Einheitliches JSON-Fehlerformat, keine Stacktraces/Pfade; globaler `set_exception_handler` fängt auch unerwartete Ausnahmen sicher ab und protokolliert sie serverseitig |

## Bekannte Fallstricke für künftige Änderungen

- **SQLite-Version des Produktivservers ist 3.7.17 (2013).** Keine Upsert-Syntax (`ON CONFLICT ... DO UPDATE`), keine Window-Functions, kein `RETURNING`. Neue Datenbank-Logik immer live testen, nicht nur lokal (siehe `architecture.md`).
- **Case-Sensitivity:** Windows-Dev-Rechner ignoriert Groß-/Kleinschreibung bei Dateipfaden, der Linux-Produktivserver nicht. Namespace-Segmente und Ordnernamen müssen exakt übereinstimmen.
- **Kein lokaler PHP-Server:** `npm run dev` proxyt gegen die Live-API. Backend-Änderungen sind erst nach einem `npm run deploy` im Frontend sichtbar.

## Vollständiger End-to-End-Test

Der komplette Hauptablauf (Aufgabe → Bestätigung → Rohstoffe → Strandhütte → Schatzsuche → Belohnung) wurde nach jeder Phase live auf `red-it.org` durchgespielt, zuletzt vollständig nach Phase 4 (siehe Commit-Historie). Ergebnis: alle Schritte funktionieren wie spezifiziert, keine doppelten Auszahlungen, korrekte Rechteprüfung.
