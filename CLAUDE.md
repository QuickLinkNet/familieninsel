# Familien-Insel – Claude Code Regeln

Vollständige Spezifikation: [`docs/product-spec.md`](docs/product-spec.md). Architektur & Begründungen: [`docs/architecture.md`](docs/architecture.md).

## Produktziel & Zielgruppe

Kooperatives Familienspiel: reale Aufgaben → Elternbestätigung → Rohstoffe → Insel-Gebäude → Minispiele. Zielfamilie: 2 Eltern (Manuel, Kathrin), 3 Kinder (Emil 5, Thea 7, Nova 8). Kein Zeitdruck, keine Bestrafung, keine Ranglisten.

## Projektgrenzen

- Eigenständiges Repository (`familieninsel`), **komplett getrennt** von `neighborhood` oder anderen Projekten.
- Kein Zugriff, keine Analyse, keine Veränderung anderer lokaler Projektverzeichnisse.
- Deployment ausschließlich nach `/html/apps/familieninsel` auf `www.red-it.org` (Alfahosting). Niemals Geschwisterverzeichnisse anfassen.

## Tech-Stack

Frontend: Vite + React + TypeScript (strict) + React Router. Backend: PHP 8.x + SQLite + PDO, kein Framework, kein Docker. Kein lokaler PHP-Dev-Server – das Frontend entwickelt gegen die live deployte API (siehe Architektur-Doku, Abschnitt "Entwicklungsmodell").

## Zentrale Spielregel

Rohstoffe werden **erst nach Elternbestätigung und genau einmal** ausgezahlt. Jede Rohstoffänderung läuft in einer SQLite-Transaktion und wird protokolliert. Alle Rollen/Rechte werden **serverseitig** geprüft – eine Frontend-Sperre allein reicht nie.

## Sicherheitsregeln

Prepared Statements überall, CSRF-Schutz auf schreibenden Endpunkten, PIN/Familiencode nur gehasht speichern, keine Secrets im Repo, keine öffentlich erreichbare SQLite-Datei, kein `dangerouslySetInnerHTML`, keine Stacktraces/Pfade in API-Fehlern.

## Arbeitsweise

- Vor größeren Änderungen bestehenden Code und Doku prüfen, nicht neu erfinden.
- Nur die angeforderte Phase umsetzen, Scope nicht eigenständig erweitern.
- Overengineering vermeiden – eine funktionierende vertikale Funktion schlägt viele unfertige Module.
- Nach Änderungen: `npm run build` und `npm test` ausführen und Ergebnis prüfen.
- Dateien möglichst < 500 Zeilen, Geschäftslogik nicht in Controllern/Komponenten verstecken.

## Befehle

```bash
npm install       # Root + Frontend-Workspace
npm run dev       # Vite-Dev-Server (spricht mit Live-API)
npm run build     # PHP-Lint + Frontend-Build + deploy/-Verzeichnis zusammenstellen
npm test          # Frontend-Tests (Vitest) + Backend-Tests (PHPUnit)
npm run deploy    # Build + sicherer FTPS-Upload nach /html/apps/familieninsel
```

Backend-Testabhängigkeiten separat installieren: `cd backend && composer install`.

## Definition of Done (pro Phase)

- Funktion ist tatsächlich nutzbar, nicht nur angelegt.
- `npm run build` läuft fehlerfrei (TypeScript + PHP-Syntax).
- Relevante Tests bestehen.
- Rollen/Rechte serverseitig geprüft, Fehlerzustände behandelt.
- Keine Zugangsdaten im Repository.
- Dokumentation (`docs/product-spec.md`, `docs/architecture.md`) bei Bedarf aktualisiert.

## Bekannte Einschränkungen (MVP)

Kein Echtzeit-Multiplayer, keine öffentliche Registrierung, keine Foto-Uploads, kein Chat, keine Ranglisten, keine In-App-Käufe, nur ein aktives Bauprojekt gleichzeitig, nur ein Minispiel im MVP.
