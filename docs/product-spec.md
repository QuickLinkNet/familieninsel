# Familien-Insel – Produktspezifikation

Referenzgrafiken: [`references/familieninsel-inselansicht.png`](references/familieninsel-inselansicht.png), [`references/familieninsel-dashboard.png`](references/familieninsel-dashboard.png), [`references/familieninsel-projektplan.png`](references/familieninsel-projektplan.png).

## 1. Vision

Familien-Insel ist ein kooperatives Familienspiel, das reale Familienaufgaben mit dem Aufbau einer virtuellen Insel verbindet. Eltern erstellen reale Aufgaben und weisen sie einem Familienmitglied zu. Rohstoffe werden erst nach Erledigung **und** Elternbestätigung ausgezahlt. Mit den gemeinsam gesammelten Rohstoffen baut die Familie Gebäude, die neue Inselinhalte oder Minispiele freischalten.

Keine Bestrafung, kein Zeitdruck, keine Ranglisten, keine Konkurrenz zwischen Kindern.

## 2. Demo-Familie

| Name | Rolle | Alter |
|---|---|---|
| Manuel | Elternteil | – |
| Kathrin | Elternteil | – |
| Emil | Kind | 5 |
| Thea | Kind | 7 |
| Nova | Kind | 8 |

Demo-Daten dürfen nicht hart in Komponenten eingebaut werden – die Anwendung muss später andere/mehr Familienmitglieder unterstützen.

## 3. Grundszenario

Die Familie strandet nach einem Sturm auf einer unbekannten Insel. Niemand ist verletzt. Das Boot ist beschädigt, enthält aber verwertbare Materialien. Ziel: Unterkunft bauen, Insel erkunden, neue Gebiete/Minispiele freischalten, langfristig einen Weg zurück finden. Ton: freundlich, abenteuerlich, humorvoll, kindgerecht, kooperativ. Kein Tod, keine Gegner, kein Zeitdruck, keine Bestrafung für verpasste Tage.

## 4. Erstes Bauprojekt: Strandhütte

Benötigte Rohstoffe (zentral konfigurierbar): 20 Holz, 10 Metall, 8 Stoff, 5 Seile.

Demo-Aufgaben beim ersten Start:

| Kind/Elternteil | Aufgabe | Belohnung |
|---|---|---|
| Emil | Spielzeug einsammeln | 3 Holz, 1 Stoff |
| Thea | Tisch decken | 2 Metall, 1 Seil |
| Nova | Eigenes Zimmer aufräumen | 5 Holz, 2 Stoff |
| Manuel | Werkzeug und Materialien sortieren | 4 Metall, 1 Seil |
| Kathrin | Vorräte für den nächsten Tag vorbereiten | 3 Holz, 2 Stoff |

Demo-Aufgaben sind durch Eltern bearbeitbar/löschbar/ersetzbar.

## 5. Zentraler Spielablauf

```text
Elternteil erstellt reale Aufgabe
→ Aufgabe wird zugewiesen, Belohnung festgelegt
→ Familienmitglied erledigt Aufgabe im echten Leben
→ Familienmitglied meldet Aufgabe als erledigt
→ Elternteil prüft und bestätigt (oder lehnt ab)
→ Rohstoffe werden EINMALIG gutgeschrieben
→ Rohstoffe landen im gemeinsamen Familienlager
→ Familie investiert Rohstoffe in das aktive Gebäude
→ Gebäude verändert sichtbar die Insel
→ Fertigstellung schaltet neuen Inhalt frei
```

Muss technisch gegen doppelte Belohnung abgesichert sein (siehe Datenmodell, `tasks.rewarded_at`).

## 6. Spielverhalten (asynchron)

Allein spielbar: Profil wählen, eigene Aufgaben ansehen/melden, Insel/Baufortschritt/Lager ansehen, freigeschaltete Minispiele spielen, Tagebuch ansehen.

Gemeinsam spielbar: Bauprojekte entscheiden, Rohstoffe investieren, Fertigstellung erleben, neue Gebiete öffnen, Minispiele spielen, Story-Ereignisse.

**Nicht Bestandteil des MVP:** Echtzeit-Multiplayer, WebSockets, gleichzeitige Figurensteuerung, Live-Chat, frei begehbare Spielwelt.

## 7. Rollen und Rechte

**Eltern** dürfen: Aufgaben erstellen/bearbeiten/löschen/zuweisen, Belohnungen bestimmen, Aufgaben bestätigen/ablehnen/wieder öffnen, Bauprojekte auswählen, Rohstoffe investieren, Einstellungen ändern, Familienmitglieder verwalten, Aktivitätsverlauf ansehen, Demo-Daten zurücksetzen.

**Kinder** dürfen: eigenes Profil wählen, eigene Aufgaben ansehen, Aufgaben als erledigt melden, Status ansehen, gemeinsame Rohstoffe/Insel/Baufortschritt ansehen, freigeschaltete Minispiele spielen, eigene Beiträge und Tagebuch ansehen.

**Kinder dürfen NICHT:** Aufgaben erstellen/bearbeiten, Belohnungen ändern, selbst bestätigen, sich selbst Rohstoffe geben, Rohstoffe/Baukosten direkt ändern, Familienmitglieder/Einstellungen verwalten, Eltern-Endpunkte aufrufen.

**Alle Rechte werden serverseitig geprüft.** Eine Sperre nur im Frontend genügt nicht.

## 8. Aufgabenstatus

```text
open → completed_pending → approved
open → completed_pending → rejected → open
```

Eltern können bei Ablehnung eine kurze Notiz ergänzen. Eine bestätigte Aufgabe darf nie erneut bestätigt/belohnt werden.

Wiederholungstypen (Datenstruktur vorbereiten, im MVP nur `einmalig` vollständig funktionsfähig): einmalig, täglich, wöchentlich, ausgewählte Wochentage.

Aufgabenvorlagen: Zimmer aufräumen, Spielsachen einsammeln, Tisch decken/abräumen, Schulranzen vorbereiten, Kleidung wegräumen, Pflanzen gießen, Wäsche sortieren, Müll wegbringen, beim Kochen helfen.

## 9. Rohstoffe

| Rohstoff | Verwendung |
|---|---|
| Holz | Gebäude, Möbel, Stege, Werkzeuge |
| Metall | Werkzeuge, Befestigungen, Reparaturen |
| Stoff | Dächer, Betten, Segel, Dekoration |
| Seil | Gebäude, Boote, Brücken, Werkzeuge |
| Sterne | Besondere Belohnung (Minispiele, Erfolge) – im MVP nur sammeln/anzeigen |

Regeln: ganze Zahlen, nie negativ, Belohnung 0–99 pro Rohstoff, mindestens ein Wert > 0, Belohnung erst nach Bestätigung, Aufgabe wird genau einmal belohnt, jede Änderung wird protokolliert (`resource_transactions`), kritische Änderungen laufen in einer SQLite-Transaktion.

## 10. Bausystem

Immer genau ein aktives Bauprojekt. Baustufen der Strandhütte: Bauplatz → Fundament → Wände → Dach → fertig. Nur Eltern investieren Rohstoffe. Vor Einzahlung: vorhandene/einzuzahlende/verbleibende Menge + erwarteter Fortschritt anzeigen. Serverseitige Prüfung + Transaktion. Fertigstellung: genau einmal auslösbar, erzeugt Tagebucheintrag, schaltet erstes Minispiel frei, verändert Insel sichtbar.

## 11. Erstes Minispiel: Schatzsuche am Strand

Freigeschaltet durch die Strandhütte. Gesucht: Muschel, Seestern, Flaschenpost, Kompass, Schlüssel. Touch + Maus, große Trefferflächen, max. 5 Suchgegenstände, kein harter Zeitdruck, Hilfebutton, kurze Runde. Erstbelohnung: 2 Sterne (einmalig). Danach beliebig oft spielbar, aber ohne weitere unbegrenzte Rohstoffbelohnung (kein Farming).

## 12. Tech-Stack

**Frontend:** Vite, React, TypeScript (strict), React Router, CSS/CSS Modules, zentrale Fetch-Abstraktion, responsive, Touch+Maus. Kein Next.js/Angular/Vue, kein serverseitiges Node, kein unnötiges State-Management, keine Game Engine.

**Backend:** PHP 8.x, SQLite, PDO, Prepared Statements, JSON-API, PHP-Sessions, klassische Webspace-Kompatibilität. Kein Laravel/Symfony/Docker/Supabase/Firebase/MySQL/PostgreSQL, keine dauerhaften Node-Prozesse.

## 13. Datenbankmodell (SQLite, `PRAGMA foreign_keys = ON`)

Kern-Tabellen: `families`, `players` (Rollen: `parent`, `child`), `resources`, `family_resources`, `tasks`, `task_rewards`, `resource_transactions`, `buildings`, `building_costs`, `family_buildings`, `building_contributions`, `minigames`, `family_minigames`, `activity_log`.

`tasks` enthält u. a. `status`, `recurrence_type`, `parent_note`, `completed_at`, `approved_at`, `approved_by_player_id`, `rewarded_at` (verhindert doppelte Auszahlung).

Details zu Feldern: siehe Migrationen in `backend/database/migrations/` (werden ab Phase 1 angelegt).

## 14. Authentifizierung

Kein E-Mail-Login, keine öffentliche Registrierung. Zwei getrennte Wege je Rolle:

- **Eltern**: Auswahl per Icon + 4-stellige PIN über ein spielinternes Zahlenfeld (kein natives HTML-Passwortfeld, keine sichtbaren Ziffern - nur gefüllte Punkte). PIN vordefiniert beim Seeding, änderbar über die Benutzerverwaltung `/familie` (`password_hash()`/`password_verify()`, Spaltenname historisch weiterhin `password_hash`). Ein Elternteil wird bei korrekter PIN direkt vollständig angemeldet - kein zusätzlicher Freischalt-Schritt mehr.
- **Kinder**: QR-Code-Login. Jedes Kind bekommt in der Benutzerverwaltung einen Login-Token, der als QR-Code (URL `/kind/{token}`) angezeigt wird. Nur der SHA-256-Hash landet in der Datenbank, der Rohwert wird nur einmalig direkt nach dem Erzeugen angezeigt. Der Code ist wiederverwendbar (kein "einmal benutzt = tot"), damit ein zurückgesetztes Tablet erneut damit angemeldet werden kann. Ein neuer Code macht den alten sofort ungültig, ändert aber nichts am Spielstand (Token und Spielerprofil sind getrennte Datensätze).
- Kind-Sessions bekommen ein langlebiges Cookie (Monate statt Browser-Laufzeit), damit das Tablet dauerhaft angemeldet bleibt.
- Familienmitglieder lassen sich vollständig in `/familie` verwalten: Name/Alter bearbeiten, Eltern-PIN setzen (auch für die jeweils andere Person, beide Eltern sind gleichberechtigt, Eingabe per Zahlenfeld mit Wiederholung zur Bestätigung), Profile deaktivieren/reaktivieren (Soft-Delete, Daten bleiben erhalten). Schutz: niemand deaktiviert sich selbst, der letzte aktive Elternteil bleibt unantastbar.

**Historisch (entfernt):** MVP startete mit einem geteilten Familiencode + Avatar-Auswahl + 4-stelliger Eltern-PIN, danach kurz durch einen freien Text-Passwort-Login ersetzt. Beides wich der aktuellen Loesung (Icon-Auswahl + 4-stellige PIN per spielinternem Zahlenfeld), weil ein geteilter Code keine echte Zuordnung "wer ist gerade angemeldet" ermöglichte und ein natives Passwortfeld sich wie ein Web-Formular statt wie Teil des Spiels anfühlte.

Sessions: Regeneration nach Login, HttpOnly, SameSite, Secure bei HTTPS, langes Idle-Timeout (Kind-Tablets sollen nicht ständig neu anmelden müssen), serverseitige Rollenprüfung, Logout. Login-Schutz: Fehlversuche bei der Eltern-PIN zählen, kurze Sperrzeit, Protokollierung, keine Auskunft welcher Teil falsch war.

## 15. API (schrittweise je Phase implementieren)

```text
POST /api/auth/family-login
POST /api/auth/select-profile
POST /api/auth/parent-unlock
POST /api/auth/logout
GET  /api/auth/session

GET  /api/family
GET  /api/players

GET    /api/tasks
GET    /api/tasks/{id}
POST   /api/tasks
PUT    /api/tasks/{id}
DELETE /api/tasks/{id}
POST   /api/tasks/{id}/complete
POST   /api/tasks/{id}/approve
POST   /api/tasks/{id}/reject

GET /api/resources
GET /api/resource-transactions

GET  /api/buildings
GET  /api/buildings/active
POST /api/buildings/{id}/start
POST /api/buildings/{id}/contribute

GET  /api/minigames
GET  /api/minigames/{key}
POST /api/minigames/{key}/complete

GET /api/activity

GET /api/health   (bereits implementiert, Phase 0)
```

Alle schreibenden Endpunkte: authentifiziert, Rollenprüfung, CSRF-Schutz, Eingabevalidierung, Transaktionen bei mehreren Datenänderungen, konsistente Fehlerantworten.

Antwortformat:

```json
{ "success": true, "data": {}, "message": "..." }
{ "success": false, "error": { "code": "TASK_ALREADY_REWARDED", "message": "..." } }
```

Keine Stacktraces/SQL/Dateipfade/Serverdetails ans Frontend.

## 16. Frontend-Screens (Übersicht)

Familienlogin, Profilauswahl, Insel-Dashboard, Kinder-Aufgabenansicht, Eltern-Dashboard, Aufgabe erstellen, Aufgabenbestätigung, Gebäudeansicht, Minispielauswahl, Familientagebuch (keine Rangliste).

## 17. Responsive Prioritäten

1. Tablet quer (≥ 1024×768) – wichtigstes Format
2. Notebook/Desktop – vollständig unterstützt
3. Tablet hochkant – vollständig bedienbar, kompaktere Insel
4. Smartphone – mindestens Login, Profilauswahl, Aufgaben ansehen/erstellen/melden/bestätigen, Ressourcen ansehen (große Inselansicht darf vereinfacht werden)

## 18. Visuelle Richtung

Hochwertiger Cartoon-Stil, freundliche Inselwelt, runde Karten, weiche Schatten, klare Ressourcen-Icons, große Avatare, bunt aber nicht überladen, kindgerecht aber nicht babyhaft. Referenzbilder in `docs/references/` dienen als Layout-/Stilrichtung, nicht als 1:1-Vorlage oder Screenshot-Hintergrund.

## 19. Deployment-Ziel

Öffentliche URL: `https://www.red-it.org/apps/familieninsel/`
Server-Zielordner: `/html/apps/familieninsel`
Hosting: Alfahosting (Plesk), FTP-Host `web17.alfahosting-server.de`.

Das Deploy-Skript arbeitet ausschließlich innerhalb dieses Ordners (siehe `scripts/deploy.mjs`). Persistente Laufzeitdaten (`storage/database`, `storage/backups`, `storage/logs`) werden von Deployments nie überschrieben.

**Abweichung vom ursprünglichen Entwicklungsmodell:** Es gibt keinen lokalen PHP-Entwicklungsserver. Das lokale Frontend (`npm run dev`) spricht direkt mit der live deployten API unter der obigen URL. Backend-Änderungen müssen deployt werden, um im Frontend sichtbar zu werden. Details und Konsequenzen siehe [`architecture.md`](architecture.md).

## 20. Sicherheit & Datenschutz

Prepared Statements, serverseitige Validierung/Rollenprüfung, CSRF-Schutz, sichere Sessions, PIN-/Familiencode-Hashing, keine Secrets im Repo, keine öffentlich erreichbare SQLite-Datei, Schutz vor XSS, kein `dangerouslySetInnerHTML`, maximale Eingabelängen, PIN-Rate-Limiting, Transaktionen für Rohstoffänderungen.

Keine öffentliche Registrierung, kein Chat, keine Werbung, keine In-App-Käufe, keine externen Analytics, keine Standortdaten, kein Tracking, keine Foto-Uploads. Private Familienanwendung.

## 21. Tests

Backend (pragmatisch, PHPUnit): Login/PIN-Validierung, Rollenprüfung, Aufgaben-Lebenszyklus, einmalige Belohnung, keine doppelte Bestätigung, keine negativen Rohstoffe, Gebäude/Minispiel je genau einmal fertigstellbar/freischaltbar.

Frontend (Vitest + Testing Library): Profilauswahl, Rechte-Sichtbarkeit, Aufgabenerstellung/-validierung, Statusanzeige, Ressourcen-/Baufortschrittsanzeige, Minispiel-Sperrzustand.

Vollständiger End-to-End-Ablauf (manuell oder automatisiert zu prüfen): Login → Aufgabe erstellen → erledigen → bestätigen → Rohstoffgutschrift → in Strandhütte investieren → fertigstellen → Schatzsuche freischalten/spielen → einmalige Sternebelohnung.

## 22. Qualitätsregeln

TypeScript strict, kein unnötiges `any`, Dateien < 500 Zeilen, Geschäftslogik nicht in Controllern/Komponenten, SQL nur in Repositories, zentrale Typdefinitionen/API-Abstraktion, Lade-/Fehler-/Leerzustände, keine unnötigen Abhängigkeiten, kein toter Code, keine vorgetäuschten Funktionen.
