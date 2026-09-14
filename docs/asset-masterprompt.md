# Masterprompt für Bild-Assets – Familien-Insel

## ✅ Status: Prompts 1–19 fertig und im Spiel verbaut, 20 (Login-Karten-Textur) ist neu und offen

Prompts 1-19 sind fertig unter `input/assets/` (insel-karte.png, rahmen-erwachsen.png,
rahmen-kind.png, strandhuette-1-bauplatz.png … strandhuette-5-fertig.png, icon-holz.png,
icon-metall.png, icon-stoff.png, icon-seil.png, icon-stern.png, wachturm-1-bauplatz.png …
wachturm-5-fertig.png, login-hintergrund.png). Rohentwürfe (vor dem lokalen Alpha-Fix) liegen zur
Referenz unter `input/assets/_archiv/`.

**Neu und noch offen: Prompt 20** – eine Holzschild-Textur als Hintergrund für die Login-Karte
selbst (Avatar-Auswahl + Passwortfeld), damit die Karte wie ein Objekt aus der Inselwelt wirkt statt
wie ein aufgesetztes CSS-Rechteck. Speichern unter `input/assets/login-karte-textur.png`. Danach
Bescheid sagen, dann wird sie per WebP-Pipeline als `background-image` von `.auth-panel` eingebaut.

Die Prompts bleiben insgesamt als Vorlage fuer **zukuenftige Inseln/Gebaeude** im Dokument stehen.

## ⚠️ Wichtigste Regel, unbedingt zuerst lesen

**Dieses Dokument NIEMALS als Ganzes in ChatGPT einfügen.** Genau das ist beim ersten Versuch passiert – ChatGPT hat das komplette Dokument als "eine Bildbeschreibung" gelesen und ein hübsches Infografik-Poster *über* die Spezifikation gemalt statt der eigentlichen Assets. Kein einziges der 4 Asset-Typen kam dabei als echte, nutzbare Bilddatei heraus.

Richtig geht es so: Weiter unten stehen **20 einzelne, nummerierte Prompts** (Prompt 1 bis 20). Jeder davon ist eine **eigene, abgeschlossene Nachricht**. Vorgehen:

1. Nur den Text von **Prompt 1** kopieren (alles zwischen den `---PROMPT---`-Markierungen, nichts drumherum).
2. Als eigene Nachricht an ChatGPT schicken, Referenzbild(er) anhängen falls angegeben.
3. Warten, bis das fertige Bild da ist, herunterladen.
4. Erst dann mit **Prompt 2** weitermachen, usw.

Nie zwei Prompts in einer Nachricht kombinieren, nie eigene Kommentare/Überschriften mit einfügen – je "sauberer" die einzelne Nachricht nur den Bildauftrag enthält, desto zuverlässiger wird ein echtes Bild draus statt einer Illustration über den Text.

## Vorgeschichte – warum es diese 13 Einzel-Prompts gibt

Eine erste Asset-Lieferung existiert unter `input/assets/`. Bewertung:

- **Insel-Karte** und die **3 Gebäude-Zustände** darin sind stilistisch gut, mit echter Transparenz am Rand – als Stil-Referenz weiterverwenden (werden unten als Bildanhang referenziert).
- **Charakter-Portraits werden nicht mehr gebraucht** – Familienmitglieder laden später ihr eigenes Foto hoch. Gebraucht wird nur ein **Rahmen** drumherum.
- Das **Rohstoff-Icon-Sheet** (6 Icons auf einem Bild) muss als **einzelne Dateien** kommen – deshalb jetzt 5 einzelne Prompts statt einem Sammel-Prompt.
- Die Gebäude-Bilder passten **nicht** in Kameraperspektive/Beleuchtung zur Insel-Karte. Deshalb fordert jeder Baustufen-Prompt das jetzt explizit unter Bezug auf die Insel-Karte als Referenzbild ein.
- Die 5 Baustufen wurden diesmal bewusst in **5 einzelne Prompts** aufgeteilt (Prompt 4–8), nicht "erstelle 5 Bilder" in einem – sonst besteht dasselbe Risiko wie beim Icon-Sheet: ein Sammelbild/eine Collage statt 5 einzelner Dateien.

## Grundlegender Kunststil (in jedem Prompt unten schon enthalten)

> Hochwertiger, semi-realistischer Isometrie-Illustrationsstil für ein familienfreundliches Aufbauspiel (vergleichbar mit mobilen Aufbauspielen wie Township oder Klondike). Warmes, sonniges Licht von schräg oben, weiche Schlagschatten, satte aber nicht grelle Farben, saubere Kanten. Kein Fotorealismus, keine Personen/Gesichter, kein Comic-/Anime-Stil. Tropische Insel-Ästhetik: Palmen, Sand, türkises Wasser, verwittertes Holz, Seile, Stroh.

---

## Prompt 1 von 13 – Hauptinsel-Karte

Referenzbild anhängen: `input/assets/85173047-2974-4691-ad32-4f3776b0e402.png`

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil für ein
familienfreundliches Aufbauspiel. Warmes, sonniges Licht von schräg
oben, weiche Schlagschatten, satte aber nicht grelle Farben, saubere
Kanten. Kein Fotorealismus, keine Personen, kein Comic-/Anime-Stil.

Erstelle eine isometrische Ansicht einer tropischen Insel von schräg
oben, im exakt gleichen Stil wie das angehängte Referenzbild. Die Insel
soll GRÖSSER und weitläufiger sein als im Referenzbild, mit klar
sichtbarem Platz für mehrere zukünftige Gebäude - nicht nur einen
Bauplatz.

Enthalten:
- 4 bis 6 klar erkennbare, leere Bauplätze/Lichtungen an
  unterschiedlichen Stellen der Insel (unterschiedliche Größen, an
  Wegen liegend, gut voneinander unterscheidbar)
- Ein zentrales Wegenetz, das die Bauplätze verbindet
- Bootssteg am Wasser, Wasserfall/Felsformation als Landmarke,
  unterschiedliche Vegetationszonen (Strand, Gras, Wald)
- Gleichbleibende Lichtrichtung wie im Referenzbild (Sonne von oben
  rechts)
- Keine Personen, keine Texte/Schilder mit Schrift im Bild

Format: Querformat, mindestens 2400x1600px, PNG. Das Bild soll
randabschneidend/vollflächig sein (die Insel darf am Bildrand
angeschnitten sein) - kein Vignette-/Rahmeneffekt wie im Referenzbild,
da dies ein zoombarer Kartenhintergrund wird, kein Icon.
```
---ENDE PROMPT---

---

## Prompt 2 von 13 – Foto-Rahmen, Variante Erwachsene

Referenzbild anhängen: eines der Portrait-Bilder aus `input/assets/` (z. B. `474c12b2-a411-4016-b920-b8611b75bccd.png`) – nur wegen Rahmen/Blumen-Stil, Gesicht ignorieren.

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht, saubere Kanten.

Erstelle NUR einen dekorativen kreisrunden Bilderrahmen im exakt
gleichen Stil wie der Rahmen im angehängten Referenzbild (goldener Ring
mit tropischen Blumen/Blättern an einer Seite) - ignoriere das Gesicht
und den Innenhintergrund des Referenzbilds komplett, die werden nicht
gebraucht.

Der komplette Innenbereich des Rings muss vollständig transparent sein
(Alpha = 0), dort wird später ein Foto eingesetzt. Warmer Goldton,
Hibiskusblüte, für ein erwachsenes Familienmitglied gedacht.

Format: quadratisch, mindestens 1024x1024px, PNG mit Alpha-Kanal.
Außerhalb des Rings ebenfalls vollständig transparent, kein Kasten,
keine Vignette, kein Hintergrund.
```
---ENDE PROMPT---

## Prompt 3 von 13 – Foto-Rahmen, Variante Kinder

Gleiches Referenzbild wie Prompt 2 anhängen.

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht, saubere Kanten.

Erstelle NUR einen dekorativen kreisrunden Bilderrahmen im exakt
gleichen Stil wie der Rahmen im angehängten Referenzbild (Ring mit
tropischen Blumen/Blättern an einer Seite) - ignoriere Gesicht und
Innenhintergrund komplett.

Der komplette Innenbereich des Rings muss vollständig transparent sein
(Alpha = 0), dort wird später ein Foto eingesetzt. Hellerer, verspielter
Grünton, Plumeria-Blüte (weiß/gelb), für ein Kind gedacht.

Format: quadratisch, mindestens 1024x1024px, PNG mit Alpha-Kanal.
Außerhalb des Rings ebenfalls vollständig transparent, kein Kasten,
keine Vignette, kein Hintergrund.
```
---ENDE PROMPT---

---

## Prompt 4 von 13 – Strandhütte Baustufe 1: Bauplatz

Referenzbilder anhängen: `input/assets/insel-karte.png` UND `input/assets/entwuerfe/strandhuette-1-bauplatz.png` (aktuelles Baustufe-1-Ergebnis, Inhalt/Komposition gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: einen abgesteckten leeren Bauplatz (Seil-Absperrung
an Holzpfählen, Holzschild in der Mitte, Werkzeug/Bretter-Stapel am
Rand), inhaltlich und kompositorisch identisch zum zweiten angehängten
Referenzbild - ABER ohne dessen Hintergrund-Glow/Vignette. Die Kamera-
perspektive, Zoomstufe und Lichtrichtung müssen exakt zur Insel-Karte
(erstes angehängtes Bild) passen, damit dieses Bild direkt - nur
skaliert, ohne Verzerrung - auf einen Bauplatz der Insel-Karte gelegt
werden kann.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Bauplatzes selbst und seines direkten Schlagschattens. Harter
Alpha-Schnitt direkt an der Objektkante (inklusive Schlagschatten) -
keinerlei radialer Verlauf, Aufhellung oder Verdunklung im
transparenten Bereich, auch nicht leicht angedeutet oder als Glow. Der
Übergang von sichtbar zu transparent ist abrupt, wie ein
Freisteller/Cutout, NICHT wie ein Foto-Vignetten- oder Glow-Effekt.
Keine eigene Vignette, kein Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 5 von 13 – Strandhütte Baustufe 2: Fundament

Referenzbilder anhängen: `input/assets/insel-karte.png` UND `input/assets/entwuerfe/strandhuette-2-fundament.png` (aktuelles Ergebnis, Inhalt/Komposition gut, Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: denselben Bauplatz wie zuvor, jetzt mit sichtbarem
Steinfundament und eingerammten Holz-Grundpfählen, aber noch ohne
aufgerichtetes Holzgerüst. Kameraperspektive, Zoomstufe und
Lichtrichtung exakt wie im angehängten Insel-Karten-Referenzbild, damit
das Bild direkt auf einen Bauplatz der Karte gelegt werden kann.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Gebäudes und seines Schlagschattens. Harter Alpha-Schnitt direkt an
der Objektkante (inklusive Schlagschatten) - keinerlei radialer
Verlauf, Aufhellung oder Verdunklung im transparenten Bereich, auch
nicht leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 6 von 13 – Strandhütte Baustufe 3: Rahmen/Rohbau

Referenzbilder anhängen: `input/assets/insel-karte.png` UND `input/assets/entwuerfe/strandhuette-3-rohbau.png` (aktuelles Baustufe-3-Ergebnis, Inhalt/Komposition gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: ein Holzgerüst mit Dachstuhl auf demselben Bauplatz,
inhaltlich und kompositorisch identisch zum zweiten angehängten
Referenzbild - ABER ohne dessen Hintergrund-Glow/Vignette - noch OHNE
Reet-Dach und ohne Wände. Kameraperspektive, Zoomstufe und
Lichtrichtung exakt wie im ersten angehängten Insel-Karten-Bild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Gebäudes und seines Schlagschattens. Harter Alpha-Schnitt direkt an
der Objektkante (inklusive Schlagschatten) - keinerlei radialer
Verlauf, Aufhellung oder Verdunklung im transparenten Bereich, auch
nicht leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 7 von 13 – Strandhütte Baustufe 4: Dach

Referenzbilder anhängen: `input/assets/insel-karte.png` UND `input/assets/entwuerfe/strandhuette-4-dach.png` (aktuelles Baustufe-4-Ergebnis, Inhalt/Komposition gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: dasselbe Holzgerüst, jetzt mit fertigem Reet-Dach,
aber die Wände noch offen/unfertig (noch keine geschlossenen
Wandflächen) - inhaltlich und kompositorisch identisch zum zweiten
angehängten Referenzbild, ABER ohne dessen Hintergrund-Glow/Vignette.
Kameraperspektive, Zoomstufe und Lichtrichtung exakt wie im ersten
angehängten Insel-Karten-Bild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Gebäudes und seines Schlagschattens. Harter Alpha-Schnitt direkt an
der Objektkante (inklusive Schlagschatten) - keinerlei radialer
Verlauf, Aufhellung oder Verdunklung im transparenten Bereich, auch
nicht leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 8 von 13 – Strandhütte Baustufe 5: Fertig

Referenzbilder anhängen: `input/assets/insel-karte.png` UND `input/assets/entwuerfe/strandhuette-5-fertig.png` (aktuelles Baustufe-5-Ergebnis, Inhalt/Komposition gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: die vollständig fertige, gemütliche Strandhütte,
inhaltlich und kompositorisch identisch zum zweiten angehängten
Referenzbild, ABER ohne dessen Hintergrund-Glow/Vignette, auf demselben
Bauplatz. Kameraperspektive, Zoomstufe und Lichtrichtung exakt wie im
ersten angehängten Insel-Karten-Bild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Gebäudes und seines Schlagschattens. Harter Alpha-Schnitt direkt an
der Objektkante (inklusive Schlagschatten) - keinerlei radialer
Verlauf, Aufhellung oder Verdunklung im transparenten Bereich, auch
nicht leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

---

## Prompt 9 von 13 – Icon: Holz

Referenzbild anhängen: `input/assets/entwuerfe/icon-holz.png` (aktuelles Holz-Icon, Inhalt gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, warmes Licht,
saubere Kanten. Erstelle EIN einzelnes Icon: ein Stapel Baumstämme
(Holz-Rohstoff), inhaltlich identisch zum angehängten Referenzbild,
ABER ohne dessen Hintergrund-Glow/Vignette. Objekt zentriert, ca. 10%
Freiraum zum Bildrand.

Vollständig transparenter Hintergrund (Alpha = 0). Harter Alpha-Schnitt
direkt an der Objektkante - keinerlei radialer Verlauf, Aufhellung
oder Verdunklung im transparenten Bereich, auch nicht leicht
angedeutet oder als Glow. Der Übergang von sichtbar zu transparent ist
abrupt, wie ein Freisteller/Cutout, NICHT wie ein Glow-Effekt. PNG,
mindestens 512x512px. Kein Sammelbild, nur dieses eine Icon.
```
---ENDE PROMPT---

## Prompt 10 von 13 – Icon: Metall

Referenzbild anhängen: `input/assets/entwuerfe/icon-metall.png` (aktuelles Metall-Icon, Inhalt gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, warmes Licht,
saubere Kanten. Erstelle EIN einzelnes Icon: ein goldenes Zahnrad mit
Schrauben/Muttern (Metall-Rohstoff), inhaltlich identisch zum
angehängten Referenzbild, ABER ohne dessen Hintergrund-Glow/Vignette.
Objekt zentriert, ca. 10% Freiraum zum Bildrand.

Vollständig transparenter Hintergrund (Alpha = 0). Harter Alpha-Schnitt
direkt an der Objektkante - keinerlei radialer Verlauf, Aufhellung
oder Verdunklung im transparenten Bereich, auch nicht leicht
angedeutet oder als Glow. Der Übergang von sichtbar zu transparent ist
abrupt, wie ein Freisteller/Cutout, NICHT wie ein Glow-Effekt. PNG,
mindestens 512x512px. Kein Sammelbild, nur dieses eine Icon.
```
---ENDE PROMPT---

## Prompt 11 von 13 – Icon: Stoff

Referenzbild anhängen: `input/assets/entwuerfe/icon-stoff.png` (aktuelles Stoff-Icon, Inhalt gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, warmes Licht,
saubere Kanten. Erstelle EIN einzelnes Icon: gefaltete Decken/Tücher
(Stoff-Rohstoff), inhaltlich identisch zum angehängten Referenzbild,
ABER ohne dessen Hintergrund-Glow/Vignette. Objekt zentriert, ca. 10%
Freiraum zum Bildrand.

Vollständig transparenter Hintergrund (Alpha = 0). Harter Alpha-Schnitt
direkt an der Objektkante - keinerlei radialer Verlauf, Aufhellung
oder Verdunklung im transparenten Bereich, auch nicht leicht
angedeutet oder als Glow. Der Übergang von sichtbar zu transparent ist
abrupt, wie ein Freisteller/Cutout, NICHT wie ein Glow-Effekt. PNG,
mindestens 512x512px. Kein Sammelbild, nur dieses eine Icon.
```
---ENDE PROMPT---

## Prompt 12 von 13 – Icon: Seil

Referenzbild anhängen: `input/assets/entwuerfe/icon-seil.png` (aktuelles Seil-Icon, Inhalt gut, nur der Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, warmes Licht,
saubere Kanten. Erstelle EIN einzelnes Icon: eine aufgerollte
Seilspule (Seil-Rohstoff), inhaltlich identisch zum angehängten
Referenzbild, ABER ohne dessen Hintergrund-Glow/Vignette. Objekt
zentriert, ca. 10% Freiraum zum Bildrand.

Vollständig transparenter Hintergrund (Alpha = 0). Harter Alpha-Schnitt
direkt an der Objektkante - keinerlei radialer Verlauf, Aufhellung
oder Verdunklung im transparenten Bereich, auch nicht leicht
angedeutet oder als Glow. Der Übergang von sichtbar zu transparent ist
abrupt, wie ein Freisteller/Cutout, NICHT wie ein Glow-Effekt. PNG,
mindestens 512x512px. Kein Sammelbild, nur dieses eine Icon.
```
---ENDE PROMPT---

## Prompt 13 von 13 – Icon: Stern

Referenzbild anhängen: `input/assets/entwuerfe/icon-stern.png` (aktuelles Ergebnis, Inhalt gut, Glow muss weg)

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, warmes Licht,
saubere Kanten. Erstelle EIN einzelnes Icon: ein goldener glänzender
Stern (besondere Belohnung), im Stil des Stern-Icons (unten rechts) im
angehängten Referenzbild. Objekt zentriert, ca. 10% Freiraum zum
Bildrand.

Vollständig transparenter Hintergrund (Alpha = 0). Harter Alpha-Schnitt
direkt an der Objektkante - keinerlei radialer Verlauf, Aufhellung
oder Verdunklung im transparenten Bereich, auch nicht leicht
angedeutet oder als Glow. Der Übergang von sichtbar zu transparent ist
abrupt, wie ein Freisteller/Cutout, NICHT wie ein Glow-Effekt. PNG,
mindestens 512x512px. Kein Sammelbild, nur dieses eine Icon.
```
---ENDE PROMPT---

---

## Prompt 14 von 18 – Wachturm Baustufe 1: Bauplatz

Referenzbild anhängen: die Insel-Karte (`insel-karte.png`).

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: einen abgesteckten leeren Bauplatz (Seil-Absperrung
an Holzpfählen, Holzschild in der Mitte, ein Stapel Holzbalken und
Werkzeug am Rand), im Stil des angehängten Insel-Karten-Referenzbilds.
Kameraperspektive, Zoomstufe und Lichtrichtung müssen exakt zur
Insel-Karte passen, damit dieses Bild direkt - nur skaliert, ohne
Verzerrung - auf einen Bauplatz der Insel-Karte gelegt werden kann.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Bauplatzes selbst und seines direkten Schlagschattens. Harter
Alpha-Schnitt direkt an der Objektkante (inklusive Schlagschatten) -
keinerlei radialer Verlauf, Aufhellung oder Verdunklung im
transparenten Bereich, auch nicht leicht angedeutet oder als Glow. Der
Übergang von sichtbar zu transparent ist abrupt, wie ein
Freisteller/Cutout, NICHT wie ein Foto-Vignetten- oder Glow-Effekt.
Keine eigene Vignette, kein Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 15 von 18 – Wachturm Baustufe 2: Fundament

Referenzbild anhängen: die Insel-Karte (`insel-karte.png`).

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: denselben Bauplatz wie zuvor, jetzt mit einem
kreisrunden bis quadratischen Steinfundament und vier tief eingerammten,
dicken Holz-Grundpfählen (die spaeter die Turmbeine werden), aber noch
ohne aufgerichtetes Gerüst. Kameraperspektive, Zoomstufe und
Lichtrichtung exakt wie im angehängten Insel-Karten-Referenzbild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Fundaments und seines Schlagschattens. Harter Alpha-Schnitt direkt an
der Objektkante (inklusive Schlagschatten) - keinerlei radialer
Verlauf, Aufhellung oder Verdunklung im transparenten Bereich, auch
nicht leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 16 von 18 – Wachturm Baustufe 3: Gerüst

Referenzbild anhängen: die Insel-Karte (`insel-karte.png`).

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: auf demselben Fundament ein hohes, schlankes
Holzgerüst aus vier Eckpfosten mit diagonalen Verstrebungen (wie ein
Leiter-/Gitter-Turm), das deutlich höher ist als ein normales Gebäude,
aber noch OHNE Aussichtsplattform oben und ohne Geländer. Kamera-
perspektive, Zoomstufe und Lichtrichtung exakt wie im angehängten
Insel-Karten-Referenzbild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Gerüsts und seines Schlagschattens. Harter Alpha-Schnitt direkt an der
Objektkante (inklusive Schlagschatten) - keinerlei radialer Verlauf,
Aufhellung oder Verdunklung im transparenten Bereich, auch nicht
leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 17 von 18 – Wachturm Baustufe 4: Plattform

Referenzbild anhängen: die Insel-Karte (`insel-karte.png`).

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: dasselbe hohe Holzgerüst wie zuvor, jetzt mit einer
fertigen Aussichtsplattform und Geländer oben sowie einer Leiter an
der Seite, aber noch OHNE Dach und ohne Flagge, Fass oder sonstige
Dekoration. Kameraperspektive, Zoomstufe und Lichtrichtung exakt wie
im angehängten Insel-Karten-Referenzbild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Turms und seines Schlagschattens. Harter Alpha-Schnitt direkt an der
Objektkante (inklusive Schlagschatten) - keinerlei radialer Verlauf,
Aufhellung oder Verdunklung im transparenten Bereich, auch nicht
leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

## Prompt 18 von 18 – Wachturm Baustufe 5: Fertig

Referenzbild anhängen: die Insel-Karte (`insel-karte.png`).

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht von schräg oben, weiche Schlagschatten.

Erstelle EIN Bild: den vollständig fertigen Wachturm auf demselben
Fundament - Aussichtsplattform mit Geländer, kleines Reetdach als
Sonnenschutz oben, eine gehisste Stoff-Flagge, ein Fernrohr oder
Fass mit Seilrollen auf der Plattform, Leiter an der Seite.
Kameraperspektive, Zoomstufe und Lichtrichtung exakt wie im
angehängten Insel-Karten-Referenzbild.

Vollständig transparenter Hintergrund (Alpha = 0) außerhalb des
Turms und seines Schlagschattens. Harter Alpha-Schnitt direkt an der
Objektkante (inklusive Schlagschatten) - keinerlei radialer Verlauf,
Aufhellung oder Verdunklung im transparenten Bereich, auch nicht
leicht angedeutet oder als Glow. Der Übergang von sichtbar zu
transparent ist abrupt, wie ein Freisteller/Cutout, NICHT wie ein
Foto-Vignetten- oder Glow-Effekt. Keine eigene Vignette, kein
Bildrahmen. PNG, mindestens 1024x1024px.
```
---ENDE PROMPT---

---

## Prompt 19 von 19 – Login-Hintergrund "Familien-Insel" (v2)

**v1-Ergebnis war stimmungsvoll und gut, aber als 3:4-Hochformat mit bildfüllendem Bogen nicht als
EIN Hintergrund für Desktop UND Handy gleichzeitig nutzbar** (CSS `background-size: cover` hätte
auf breiten Screens oben/unten weggeschnitten - Bogen abgeschnitten -, auf Handys links/rechts).
v2 behebt das mit einer **quadratischen Leinwand**, auf der der Bogen nur die mittleren ~50%
einnimmt, umgeben von reichlich Füllbereich, der beliebig weggeschnitten werden kann.

Referenzbild anhängen: `input/assets/insel-karte.png` (nur für Stil-/Welt-Kontinuität – gleiche Insel, gleicher Look, aber ein neues, stimmungsvolleres Bild, keine Kopie der Kartenansicht). Falls v1 schon existiert: zusätzlich als zweites Referenzbild anhängen ("gleicher Look/gleiche Szene, nur andere Bildkomposition").

---PROMPT---
```text
Hochwertiger, semi-realistischer Isometrie-Illustrationsstil für ein
familienfreundliches Aufbauspiel. Warmes, goldenes Licht, weiche
Schlagschatten, satte aber nicht grelle Farben, saubere Kanten. Kein
Fotorealismus, keine Personen/Gesichter, kein Comic-/Anime-Stil.
Tropische Insel-Ästhetik: Palmen, Sand, türkises Wasser, verwittertes
Holz, Seile, Stroh - dieselbe Insel-Welt wie im angehängten
Referenzbild. Später Nachmittag/goldene Stunde, lange weiche Schatten,
leichter Dunst über dem Wasser, stimmungsvoll und einladend.

WICHTIG - Bildformat und Sicherheitszone: Das Bild ist eine QUADRATISCHE
Leinwand (1:1). Es wird später sowohl auf sehr breiten Desktop-Bildschirmen
(Seitenverhältnis ca. 16:9) als auch auf sehr hohen Handy-Bildschirmen
(Seitenverhältnis ca. 9:19) mittig zugeschnitten (CSS
"background-size: cover", zentriert) - in BEIDEN Faellen darf nichts
Wichtiges verloren gehen. Deshalb gilt strikt:

- Das komplette Hauptmotiv (siehe unten: Bogen + Schild + unmittelbarer
  Weg davor) muss vollstaendig innerhalb der MITTLEREN 50% der Breite
  UND der MITTLEREN 50% der Hoehe der Leinwand liegen (also mittig, mit
  jeweils ca. 25% Abstand zu jedem der vier Raender).
- Die aeusseren 25%-Randstreifen auf allen vier Seiten sind ein
  "sicherer Fuellbereich": dort NUR ergaenzende Umgebung, die beliebig
  angeschnitten werden darf, ohne dass eine wichtige Szene verloren
  geht - z.B. mehr Himmel/Wolken oben, mehr Sand/seichtes Wasser
  unten, mehr Palmen/Dschungel-Rand links und rechts. Diese Bereiche
  muessen trotzdem hochwertig und stimmig ausgemalt sein (keine leeren
  Flaechen), duerfen aber keine für die Szene unverzichtbaren Objekte
  enthalten.

Hauptmotiv (in der zentralen Sicherheitszone):
- Ein handgeschnitztes hölzernes Willkommenstor/Bogen aus verwittertem
  Holz und Tauwerk, mit tropischen Blumen berankt, mittig auf einem
  sandigen Weg. Am Bogen ein rustikales Holzschild mit groben,
  handgemalten/eingebrannten Buchstaben: deutlich lesbar der
  Schriftzug "FAMILIEN-INSEL" - wie ein von Hand geschnitztes
  Wirtshausschild, nicht wie digitale Typografie.
- Der Bogen selbst darf nicht höher als ca. 40% der Bildhöhe und nicht
  breiter als ca. 40% der Bildbreite sein, damit auch bei einem sehr
  schmalen Hochformat-Ausschnitt (nur die mittleren ca. 25% der Breite
  sichtbar) der komplette Bogen inklusive Schild sichtbar bleibt.
- Direkt um den Bogen: Bootssteg, Palmen, ein Stück türkises Wasser,
  etwas Wasserfall/Felsformation im Hintergrund - als Rahmen um das
  Hauptmotiv, nicht als eigenständige, für den Crop wichtige Elemente.

Unterer Bildbereich (unteres Drittel, horizontal zentriert): ruhiger
und weniger detailreich halten (Sand/Weg, keine harten Kontrastkanten)
- dort liegt später eine halbtransparente Anmelde-Karte über dem Bild.

Keine Personen, keine weiteren Texte/Schilder im Bild ausser dem einen
Willkommensschild.

Format: quadratisch, mindestens 3000x3000px, PNG oder JPG, vollflächig,
kein Vignette-/Rahmeneffekt am Bildrand.
```
---ENDE PROMPT---

**Falls die Schrift auf dem Schild missrät (unleserlich/verzerrt, häufige KI-Schwäche bei
Schriftzügen):** Bild trotzdem behalten, wenn Komposition/Stimmung passen - der Schriftzug kann
notfalls unauffällig wegretuschiert oder einfach so gelassen werden, die App zeigt "Familien-Insel"
ohnehin zusätzlich als echten HTML-Titel über dem Bild. Kein Grund, deswegen den ganzen Prompt zu
wiederholen.

**Falls selbst die quadratische Version auf extremen Bildschirmformaten (z.B. sehr breites
Ultrawide-Monitor-Fenster) noch zu knapp beschnitten wirkt:** Alternative statt eines einzigen
Bildes fuer alle Geraete - zwei Varianten anfordern (separater Prompt-Durchlauf mit demselben
Motiv, einmal explizit "Querformat 16:9" fuer Desktop, einmal "Hochformat 9:16" fuer Handy) und im
Frontend per CSS-Media-Query zwischen beiden umschalten. Fuer eine private Familien-App ist das
aber vermutlich mehr Aufwand als noetig - die quadratische Loesung mit Sicherheitszone sollte in
der Praxis für alle gaengigen Geraete gut genug aussehen.

---

## Prompt 20 von 20 – Login-Karten-Hintergrund (Holzschild-Textur)

Die Login-Karte selbst (Avatar-Auswahl + Passwortfeld) ist aktuell ein reines CSS-Rechteck
(Verlaufsfarbe + goldener Rahmen) - passt farblich schon, wirkt aber wie aufgesetzte UI statt wie
ein Objekt aus der Inselwelt. Dieser Prompt liefert eine echte Holzschild-Textur als Kartenhintergrund.

Referenzbilder anhängen: `input/assets/login-hintergrund.png` (Holz-/Seil-Stil des Bogens) UND
idealerweise ein Screenshot der aktuellen Karte (Farbgebung/Proportionen als Orientierung).

---PROMPT---
```text
Hochwertiger, semi-realistischer Illustrationsstil, tropische
Insel-Ästhetik, warmes Licht, dieselbe Holz-/Seil-Machart wie das
Willkommenstor im angehängten Referenzbild.

Erstelle EINE hochkant-rechteckige Holzschild-Textur, gedacht als
Hintergrund für eine Login-Karte (Avatar-Auswahl + Textfelder werden
später per HTML/CSS darüber gelegt) - NICHT als fertiges UI-Element,
nur die Textur/das Objekt selbst.

Bildaufbau:
- Mehrere breite, leicht unterschiedlich verwitterte Holzplanken
  senkrecht nebeneinander, mit Seilbindung/Lederriemen an den vier
  Ecken und den Rändern (wie das Schild am Bogen), warmes Braun- und
  Goldton, feine Holzmaserung, dezente Kerbenränder.
- Die MITTLEREN ca. 70% der Fläche (horizontal und vertikal) müssen
  RUHIG und GLEICHMÄSSIG bleiben - nur dezente Holzmaserung, keine
  Astlöcher, Kratzer, Schatten oder Farbflecken, die mit hellem Text
  oder Buttons kollidieren könnten. Dort wird später Text und mehrere
  Buttons platziert.
- Nur am äusseren Rand (die restlichen ca. 15% auf jeder Seite)
  dekorative Details: Seilbindung, kleine geschnitzte Ranken/Blüten in
  den vier Ecken (wie am Bogen), evtl. kleine Metallbeschläge.
- KEIN Text, KEINE Buchstaben, KEINE Icons, KEINE Personen im Bild -
  reine Textur/Objekt, der Text kommt später separat per Code dazu.
- Gleichmässige Beleuchtung über die ganze Fläche (kein harter
  Schlagschatten mittig), damit Text in JEDEM Bereich der Mitte gut
  lesbar bleibt, egal ob hell oder dunkel gerendert.

Format: Hochformat (schmaler als hoch), Seitenverhältnis ca. 4:5,
mindestens 1600x2000px, PNG oder JPG, vollflächig (die Textur darf am
Bildrand angeschnitten sein), kein Vignette-Effekt.
```
---ENDE PROMPT---

Speichern als `input/assets/login-karte-textur.png`. Danach Bescheid sagen - wird als
`background-image` der `.auth-panel` eingebaut (Verlaufsfarbe bleibt als Fallback/Abdunkelung
darüber, damit Text auf jeder Holzfarbe lesbar bleibt).

---

## Technische Zusammenfassung

| # | Asset | Mindestgröße | Transparenz |
|---|---|---|---|
| 1 | Insel-Karte | 2400×1600 | Nein (vollflächig) |
| 2–3 | Foto-Rahmen (Erwachsen/Kind) | 1024×1024 | Ja, innen UND außen |
| 4–8 | Strandhütte Baustufe 1–5 | 1024×1024 | Ja, außen |
| 9–13 | Rohstoff-Icons | 512×512 | Ja, außen |
| 14–18 | Wachturm Baustufe 1–5 | 1024×1024 | Ja, außen |
| 19 | Login-Hintergrund | 3000×3000 (quadratisch) | Nein (vollflächig) |
| 20 | Login-Karten-Textur | 1600×2000 (4:5) | Nein (vollflächig) |

Dateibenennung bei Rücklieferung: `insel-karte.png`, `rahmen-erwachsen.png`, `rahmen-kind.png`, `strandhuette-1-bauplatz.png` … `strandhuette-5-fertig.png`, `icon-holz.png`, `icon-metall.png`, `icon-stoff.png`, `icon-seil.png`, `icon-stern.png`, `wachturm-1-bauplatz.png` … `wachturm-5-fertig.png`, `login-hintergrund.png`, `login-karte-textur.png`.

## Was explizit NICHT gebraucht wird

- Keine Charakter-/Personen-Portraits mehr (ersetzt durch Foto-Upload + Rahmen)
- Kein Sammelbild mit mehreren Icons oder Baustufen auf einer Fläche
- Keine Bilder mit eigenem Rand/Vignette/Bilderrahmen-Effekt (außer bei den Foto-Rahmen selbst)
- **Kein Infografik-Poster über diese Spezifikation** – falls ChatGPT wieder ein Übersichtsbild statt des eigentlichen Assets anbietet: neue Nachricht, nur den reinen Prompt-Text senden, keinen Kontext dazu erklären

## Ausblick (nicht Teil dieser Bestellung, nur zur Einordnung)

Sobald diese erste Insel + Strandhütte im Spiel sitzen, folgen später weitere Inseln und Gebäude im selben Stilanker – dieses Dokument sollte dafür aufgehoben werden, damit neue Inseln stilistisch zur ersten passen.

## Hinweis: der "Glow/Vignette"-Fehler war groesstenteils ein Anzeigefehler

Bei der ersten Asset-Runde wirkten Baustufen- und Icon-Bilder in der Rohvorschau, als hätten sie
einen starken radialen Glow/Vignette im Transparenzbereich (dunkler Rand, der zur Bildmitte hin
aufhellt). Das lag daran, dass die Vorschau das PNG ohne echtes Alpha-Blending anzeigt - die
Alpha-Werte gehen an den Ecken tatsächlich sauber auf 0. Erst ein echtes Alpha-Composite auf
einen soliden Hintergrund (z. B. per Pillow `Image.alpha_composite`) zeigt den wahren Look: ein
normaler, dezenter weicher Rand/Schlagschatten, meist völlig unproblematisch.

Was tatsächlich half, ohne neue Bilder generieren zu müssen: ein simpler lokaler
Alpha-Schwellenwert-Schnitt (Cutoff ca. 128 - alles darunter wird zu Alpha 0, alles darüber zu
Alpha 255) auf die bestehenden PNGs. Das schneidet den minimalen Bloom-Rand sauber weg, ohne dass
ChatGPT irgendetwas neu zeichnen muss.

**Für künftige Inseln/Gebäude:** Bilder wie gewohnt mit den obigen Prompts generieren. Falls die
Vorschau wieder einen Glow zeigt, zuerst per echtem Alpha-Composite auf einen soliden Hintergrund
prüfen (nicht nur die rohe PNG-Vorschau anschauen) - meistens reicht danach derselbe lokale
Alpha-Schwellenwert-Schnitt, statt Zeit mit Prompt-Wortlaut-Tuning gegen den Glow zu verschwenden.
