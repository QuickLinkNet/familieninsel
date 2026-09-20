# Handoff: Quest- und Belohnungssystem "Nach dem Intro"

## Wofür dieses Dokument ist

Familien-Insel hat aktuell ein technisch fertiges, aber inhaltlich noch dünnes
Aufgabensystem: Eltern legen einzelne Aufgaben mit Rohstoff-Belohnung an, ein
Familienmitglied meldet sie als erledigt, ein Elternteil bestätigt oder lehnt ab
(siehe `docs/product-spec.md`, Abschnitte 4–10, und den fertig gebauten
Onboarding-Flow in `frontend/src/features/onboarding/` mit Pico dem Papagei, der
das Kind bis zur ersten Aufgabe führt). Was fehlt, ist ein **durchdachtes
Alltagskonzept**: welche Quests es gibt, wie sie sich anfühlen sollen, wie
Rohstoff-Belohnungen und physische Belohnungen (Pokémon-Booster & Co.)
zusammenspielen, und vor allem: warum ein Kind überhaupt Lust haben sollte, für
abstrakte Dinge wie "5 Holz" eine Aufgabe zu erledigen.

Dieses Dokument ist der Input für eine andere KI-Session, die daraus ein
konkretes, in Regeln gegossenes Quest- und Belohnungskonzept machen soll –
inklusive der offenen Fragen, die noch eine bewusste Entscheidung brauchen,
statt sie stillschweigend anzunehmen.

**Zielbild des Nutzers (wörtlich zusammengefasst):** Eltern (und die Partnerin
des Nutzers, "meine Freundin") sollen Quests für die Kinder freigeben können,
mit Belohnungen versehen – mal Rohstoffe, mal physische Belohnungen wie ein
Pokémon-Booster-Pack. Eine konkrete Beispiel-Quest: ein Foto vom
aufgeräumten Kinderzimmer als Zielzustand hinterlegen, das Kind muss ein
Vergleichsfoto hochladen, wenn es fertig ist.

## Ist-Zustand (Fakten, keine Interpretation)

- **Rollen:** `parent` und `child`. Mehrere Elternteile sind bereits normal
  vorgesehen (Manuel + Kathrin existieren parallel, beide administrieren die
  ganze Familie gleichberechtigt inkl. sich gegenseitig, siehe
  `docs/product-spec.md` Abschnitt 14). Eine dritte erwachsene Person mit
  `role = 'parent'` anzulegen ist **schon heute möglich**, ohne Codeänderung –
  sie hätte dann aber automatisch **volle** Eltern-Rechte (Aufgaben anlegen,
  bestätigen, Familie verwalten, andere PINs setzen). Es gibt **keine**
  Zwischenrolle "kann Quests annehmen, aber nicht administrieren".
- **Aufgaben-Lebenszyklus:** `open → completed_pending → approved` bzw.
  `→ rejected → open` (`TaskService::completeTask/approveTask/rejectTask` in
  `backend/src/Services/TaskService.php`). Belohnung ist **ausschließlich**
  eine feste Menge pro Rohstoff, festgelegt beim Anlegen der Aufgabe, wird
  **einmalig** bei Bestätigung gutgeschrieben. Es gibt keinerlei Konzept von
  "physischer Belohnung" oder "Foto-Nachweis" im Datenmodell.
- **Rohstoffe:** Holz, Metall, Stoff, Seil (fließen ins aktive Bauprojekt) und
  **Sterne** – im Spec bereits explizit als *"Besondere Belohnung (Minispiele,
  Erfolge) – im MVP nur sammeln/anzeigen"* reserviert. Sterne haben aktuell
  **keine** Verwendung außer Anzeige. Das ist ein wichtiger Hebel, siehe unten.
- **Foto-Infrastruktur existiert schon, aber nur für Profilbilder:**
  `PlayerPhotoService` (`backend/src/Services/PlayerPhotoService.php`) nimmt
  einen Upload entgegen, validiert Größe/Typ, croppt quadratisch, speichert als
  JPEG pro Spieler (`storage/photos/{familyId}/{playerId}.jpg` – genau **ein**
  Foto pro Person, wird überschrieben). Für Vorher/Nachher- oder
  Zielzustands-Fotos pro Aufgabe reicht das nicht, aber das Muster (Upload →
  Validierung → Crop/Resize → Speichern) ist eine solide Vorlage.
- **Aktivitätsverlauf/"Tagebuch":** `ActivityLogRepository` protokolliert
  Ereignisse (`task_completed`, `task_approved`, `task_rejected`, Gebäude- und
  Minispiel-Meilensteine) und wird im Frontend als `ActivityFeed` angezeigt –
  das ist die bestehende "Erfolge sichtbar machen"-Fläche, an die sich neue
  Belohnungsarten anhängen ließen, ohne ein komplett neues UI-Element zu
  brauchen.
- **Grundprinzipien, die nicht verletzt werden dürfen** (Spec Abschnitt 1+3,
  bewusst so entschieden, nicht verhandelbar ohne Rücksprache mit dem Nutzer):
  keine Bestrafung, kein Zeitdruck, keine Ranglisten, keine Konkurrenz
  zwischen Kindern, kindgerechter freundlicher Ton, kein Tracking/Standort,
  keine Fotos verlassen die private Familien-App ohne triftigen Grund.

## Die Kernfrage: Warum sollte ein Kind Rohstoffe sammeln wollen?

Das ist die eigentliche Design-Lücke, nicht die Technik. Ein paar ehrliche
Beobachtungen dazu, als Ausgangspunkt für die andere KI – keine fertige
Antwort, aber eine Einschätzung der Optionen:

1. **Rohstoffe sind aktuell ein Familien-Ziel, kein persönliches.** Holz/Metall/
   Stoff/Seil fließen in *ein gemeinsames* Bauprojekt (die Strandhütte). Das ist
   erzählerisch schön ("wir bauen zusammen"), aber für ein 5-jähriges Kind
   vermutlich zu abstrakt als alleiniger Motivator – der Lohn der eigenen
   Anstrengung landet sichtbar in einem Topf, den alle nutzen. Das kann
   *unterstützend* wirken (Beitrag zur Familie sichtbar machen), sollte aber
   wahrscheinlich **nicht die einzige Belohnungsschiene** für ein Kind sein.
2. **Sterne sind der naheliegende Kandidat für "persönliche" Belohnung.** Sie
   sind im Spec schon als Sondercurrency ohne Verwendung angelegt. Vorschlag
   zur Prüfung: Sterne werden die **Brücke zu physischen Belohnungen**
   (Pokémon-Booster etc.), während Holz/Metall/Stoff/Seil die
   **Familien-Baufortschritt-Währung** bleiben. Zwei Währungen mit klar
   getrennter Bedeutung sind für Kinder leichter zu verstehen als eine
   vermischte ("manche Aufgaben geben Holz UND Sterne UND manchmal ein
   Booster" wird schnell unübersichtlich).
3. **Autonomie erhöht intrinsische Motivation.** Statt einer einzelnen
   zugewiesenen Pflichtaufgabe könnte ein Kind aus 2–3 aktiven Quests wählen
   dürfen. Das kostet wenig Umsetzungsaufwand (Tasks existieren schon
   parallel), verändert aber das Gefühl von "Ich muss" zu "Ich wähle".
4. **Sichtbarer Fortschritt schlägt abstrakte Zahlen.** Die Insel/Gebäude-
   Visualisierung ist bereits genau das Richtige (siehe `BuildingProgress`,
   `IslandMap`) – das sollte für die Familien-Rohstoffe die Haupt-Belohnung
   bleiben (man SIEHT was man geschafft hat), während Sterne eher wie ein
   Sparschwein für "worauf spare ich hin" funktionieren.
5. **Ehrlich zu Ende gedacht:** Für ein 5-jähriges Kind ist der eigentliche
   Motivator wahrscheinlich nicht das Rohstoff-Zählen selbst, sondern (a) die
   Aufmerksamkeit/Anerkennung der Eltern beim Bestätigen, (b) sichtbare
   Insel-Veränderung, (c) am Ende ein echtes Ding in der Hand. Das
   Belohnungssystem sollte diese drei Ebenen bewusst bedienen, nicht nur eine
   Zahl hochzählen.

**Offene Entscheidung für die andere KI:** Soll es wirklich zwei getrennte
Währungslogiken geben (Baumaterial = Familie, Sterne = Kind/Person), oder ein
anderes Modell? Wenn zwei Währungen: Wie viele Sterne = ein Pokémon-Booster,
und wer legt diesen Umrechnungskurs fest (fix im Code, oder von Eltern
konfigurierbar)?

## Physische Belohnungen: Wie ins System bringen, ohne es zu verkomplizieren?

Ein "Pokémon-Booster" existiert nicht digital – die App kann ihn nicht
automatisch ausliefern. Zwei grundsätzlich unterschiedliche Wege, die die
andere KI gegeneinander abwägen sollte:

- **A) Belohnungs-Katalog in der App, Einlösung bleibt analog.** Eltern legen
  eine Liste möglicher physischer Belohnungen mit Sterne-Preis an (z. B.
  "Pokémon-Booster – 20 Sterne"). Das Kind kann seinen Sterne-Stand mit dem
  Katalog vergleichen, "einlösen" markiert die Sterne als verbraucht und
  erzeugt einen Tagebuch-/Activity-Eintrag ("Emil hat einen Pokémon-Booster
  eingelöst!") – der eigentliche Kauf/die Übergabe passiert weiter im echten
  Leben. Vorteil: bleibt einfach, nutzt bestehende Sterne-/Activity-
  Infrastruktur fast unverändert. Nachteil: kein Lagerbestand, keine
  Lieferlogistik – das ist aber vermutlich auch gar nicht gewollt.
- **B) Physische Belohnung ist reine Eltern-Entscheidung außerhalb der App,
  App zeigt nur "X Sterne erreicht".** Minimal-Variante, kein Katalog, keine
  Einlösungs-Aktion – Eltern schauen selbst nach, wann sie eine Belohnung
  geben. Weniger Aufwand, aber auch weniger Struktur/Verbindlichkeit fürs
  Kind ("worauf spare ich eigentlich hin?").

**Empfehlung als Ausgangspunkt:** A ist wahrscheinlich die bessere Wahl, weil
sie dem Kind einen sichtbaren, planbaren "Wunschzettel" gibt – aber das ist
eine Annahme, keine Setzung. Offene Frage für die andere KI: Soll der
Belohnungs-Katalog pro Kind unterschiedlich sein (Emil will Pokémon, ein
anderes Kind will etwas anderes)? Vermutlich ja – das spricht für eine echte
kleine Datenstruktur statt eines hart codierten Katalogs.

## Foto-Vergleichs-Quests ("Zimmer wie auf dem Bild")

Das ist der anspruchsvollste neue Baustein, und er berührt Kindersicherheit –
hier bitte besonders sorgfältig denken, nicht nur elegant lösen.

**Grundmechanik, wie sie der Nutzer beschrieben hat:** Elternteil hinterlegt
ein Zielfoto ("so soll das Zimmer aussehen") bei Erstellung einer Quest. Das
Kind macht beim Melden ein eigenes Foto zum Vergleich.

Fragen, die vor der Umsetzung geklärt werden müssen:

1. **Wer entscheidet, ob es passt – Mensch oder Maschine?**
   - *Nur der Elternteil* (schaut beide Fotos nebeneinander an, bestätigt/lehnt
     ab wie heute schon bei jeder Aufgabe) ist die mit Abstand einfachste und
     sicherste Variante – keine neue Technik, kein Fehlerrisiko, passt zum
     bestehenden Vertrauensmodell der App ("Eltern bestätigen").
   - *KI-gestützter Bildvergleich* (automatischer Ähnlichkeits-Score oder
     Bildbeschreibung als Hinweis für die Eltern) ist technisch machbar, sollte
     aber wenn überhaupt nur als **Hinweis**, nie als automatische
     Ja/Nein-Entscheidung eingesetzt werden – ein Kind, das objektiv aufgeräumt
     hat, aber von einer Bilderkennung falsch bewertet wird, widerspricht der
     "keine Bestrafung/kein Frust"-Grundphilosophie der App direkt.
   - **Kindersicherheits-Hinweis, nicht optional zu übergehen:** Fotos aus
     einem Kinderzimmer sind sensible Daten. Falls jemals eine KI-Bildanalyse
     erwogen wird, muss vorher geklärt sein: läuft das Modell lokal/self-hosted
     oder würde ein Foto an einen externen Dienst geschickt? Ein Kinderzimmerfoto
     an eine externe Cloud-API zu senden, ist ein bewusster
     Datenschutz-Kompromiss, den nur der Nutzer selbst treffen darf – die
     andere KI sollte diese Entscheidung explizit dem Nutzer vorlegen, nicht
     stillschweigend eine Cloud-Vision-API einbauen.
2. **Ein Foto pro Aufgabe oder pro Aufgaben-Vorlage?** Das Zielfoto gehört
   wahrscheinlich zur *Vorlage* ("Zimmer aufräumen" als wiederverwendbare
   Quest-Art), nicht zur einzelnen Aufgabeninstanz – sonst muss jede Woche neu
   ein Zielfoto hochgeladen werden. Das spricht für ein Konzept von
   **Aufgaben-Vorlagen mit optionalem Referenzbild**, das über mehrere
   Aufgaben-Instanzen hinweg gilt (Spec Abschnitt 8 erwähnt "Aufgabenvorlagen"
   bereits als Konzept, aber ohne Foto-Feld).
3. **Speicherort/Lebensdauer:** Reicht ein Foto pro laufender Aufgabe (wird bei
   `approve`/`reject` nicht mehr gebraucht und könnte gelöscht werden), oder
   soll der Verlauf aufgehoben werden ("früher sah dein Zimmer so aus")? Für
   Speicherplatz auf einem klassischen Webspace (siehe Deployment-Realität in
   `docs/architecture.md`) lieber bewusst klein halten und alte
   Vergleichsfotos nach Bestätigung automatisch löschen, statt sie
   unbegrenzt zu sammeln.
4. **Technischer Anknüpfungspunkt:** `PlayerPhotoService` liefert die Vorlage
   für Upload/Validierung/Resize, braucht aber eine neue, von Profilbildern
   getrennte Speicherstruktur (ein Foto pro Person reicht für Profilbilder,
   aber nicht für "ein Zielfoto pro Quest-Vorlage plus ein Nachweisfoto pro
   Erledigung").

## Die Rollenfrage: Wie nimmt "die Freundin" teil?

Zwei ehrliche Optionen, technisch beide möglich, aber mit unterschiedlichen
Konsequenzen:

- **Als vollwertiger zweiter Elternteil** (wie Kathrin heute): sie kann dann
  auch Quests für die Kinder anlegen/bestätigen, PINs anderer setzen, die
  Familie verwalten. Kein Zusatzaufwand, funktioniert mit dem heutigen
  Rollenmodell sofort.
- **Als "kann Quests bekommen und erledigen, aber nicht administrieren"**
  (eine Art dritte Rolle zwischen `parent` und `child`): das ist **nicht**
  im heutigen Rollenmodell abgebildet und würde eine echte Erweiterung
  brauchen (neue Rolle, neue Rechte-Matrix, neue Prüfungen serverseitig –
  siehe Spec Abschnitt 7 "Alle Rechte werden serverseitig geprueft").

**Offene Frage für die andere KI:** Braucht der Nutzer wirklich eine neue
Rolle, oder reicht "sie ist einfach ein zweiter Elternteil mit vollen
Rechten"? Das entscheidet, ob das ein kleiner (Daten anlegen) oder ein
größerer (neue Rolle + Rechteprüfung überall) Umsetzungsschritt wird.

## Alltagsintegration – wie wird daraus eine Gewohnheit statt eine weitere App?

- **Kadenz:** Werden Quests täglich neu "freigegeben", liegen sie als fester
  Pool, oder erscheinen sie unregelmäßig? Die App ist explizit *asynchron*
  gedacht (Spec Abschnitt 6, kein Zeitdruck) – ein tägliches Pflicht-Quest mit
  Ablaufzeit würde dem widersprechen. Eher: ein kleiner, überschaubarer Pool
  offener Quests, aus dem sich das Kind bedienen kann, ohne Deadline-Druck.
- **Keine Benachrichtigungs-Spirale.** Push-Benachrichtigungen ("Du hast noch
  3 offene Quests!") würden schnell wie Mahnungen wirken – passt nicht zum
  bestehenden Ton der App. Eher: die App liegt da, wenn das Kind reinschaut,
  ohne von außen zu drängen.
- **Elternseitige Routine mitdenken:** Das Bestätigen von Aufgaben (inkl.
  Foto-Vergleich) ist selbst ein wiederkehrender Aufwand für die Eltern – wenn
  das lästig wird, bricht das System in der Praxis zusammen, unabhängig vom
  Design für die Kinder. Die andere KI sollte auch den Eltern-Workflow (wie
  viele Klicks braucht eine Bestätigung mit Fotovergleich?) mitdenken, nicht
  nur die Kind-Perspektive.
- **Wahlfreiheit statt Zwangszuweisung** (siehe Kernfrage oben) als
  wahrscheinlich wichtigster Hebel für echte Motivation ohne Druck.

## Checkliste offener Entscheidungen (für die andere KI, zum Abarbeiten)

1. Zwei-Währungen-Modell (Baumaterial = Familie/Insel, Sterne = Person/physische
   Belohnung) – ja/nein, und wenn ja: Umrechnungskurs fix oder konfigurierbar?
2. Belohnungs-Katalog für physische Belohnungen: pro Kind individuell
   konfigurierbar, wer legt ihn an (nur Eltern), wie wird "eingelöst"
   protokolliert?
3. Foto-Vergleichs-Quests: Freigabe nur durch Elternteil (empfohlen) oder auch
   KI-gestützt (falls ja: explizite Rückfrage an den Nutzer wegen
   Kinderfoto-Datenschutz, nicht stillschweigend entscheiden)?
4. Zielfoto gehört zur Aufgaben-*Vorlage* oder zur einzelnen Aufgabe?
5. Aufbewahrung/Löschung von Nachweisfotos nach Bestätigung – wie lange, wo?
6. Rolle der Partnerin: vollwertiger zweiter Elternteil oder neue,
   eingeschränktere Rolle?
7. Quest-Kadenz: fester Pool vs. täglich neu vs. von Eltern kuratiert – und wie
   viele gleichzeitig offen, damit es nicht überfordert?
8. Soll ein Kind zwischen mehreren offenen Quests wählen dürfen (Autonomie),
   oder bleibt es bei fester Zuweisung?
9. Wie sieht der Eltern-Bestätigungs-Workflow bei Foto-Quests konkret aus
   (Seite-an-Seite-Ansicht, wie viele Klicks, was bei Ablehnung)?

## Technische Anknüpfungspunkte (Kurzreferenz für die Umsetzung)

- Aufgaben-Lebenszyklus & Rohstoff-Belohnung: `backend/src/Services/TaskService.php`
- Aufgaben-Datenmodell: `backend/database/migrations/0002_create_tasks_and_resources.sql`
- Foto-Upload-Muster (Vorlage, nicht direkt wiederverwendbar): `backend/src/Services/PlayerPhotoService.php`
- Aktivitäts-/Tagebuch-Log: `backend/src/Repositories/ActivityLogRepository.php`,
  Frontend-Anzeige `frontend/src/features/island/ActivityFeed.tsx`
- Rollen/Rechte-Prüfung (serverseitig, Vorbild für eine evtl. neue Rolle):
  `backend/src/Middleware/RequireParent.php`, `RequireAuth.php`
- Bestehende Kind-Aufgabenliste/UI: `frontend/src/features/tasks/ChildTaskList.tsx`,
  `TaskCard.tsx`
- Onboarding/Pico-Baustein für spätere Hinweise wiederverwendbar:
  `frontend/src/features/onboarding/OnboardingSpotlight.tsx` (selector-basiert,
  nicht an eine bestimmte Komponente gebunden)
- Grundprinzipien/Leitplanken: `docs/product-spec.md` Abschnitte 1, 3, 6, 7, 20

## Was diese KI-Session NICHT tun sollte

- Keine der offenen Fragen oben stillschweigend für den Nutzer entscheiden,
  besonders nicht Punkt 3 (Kinderfoto-Datenschutz) und Punkt 6 (Rollen/Rechte).
- Keine Rangliste, kein Zeitdruck, keine Bestrafungs-Mechanik einbauen – das
  widerspricht der Grundphilosophie der App und wurde vom Nutzer nie
  angefragt.
- Keine Cloud-KI-Bilderkennung für Kinderfotos vorschlagen, ohne das als
  eigene, vom Nutzer explizit zu bestätigende Entscheidung zu markieren.
