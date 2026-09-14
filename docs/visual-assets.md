# Visuelle Assets – Familien-Insel

Ziel: weg vom aktuellen listenbasierten "Admin-Interface"-Look, hin zur Spielübersicht aus den Referenzbildern (`docs/references/familieninsel-dashboard.png`, `familieninsel-inselansicht.png`). Dieses Dokument ist eine Einkaufsliste – **keine** dieser Assets sind bisher erstellt, die aktuelle App läuft komplett mit CSS-Formen/Emoji.

Stilrichtung aus den Mockups: hochwertiger Cartoon-Stil, warme Farben, weiche Schatten, abgerundete Karten, isometrisch wirkende Insel-Szene. Nicht kindisch/billig, aber verspielt.

## Warum das aktuelle UI "administrativ" wirkt

Es fehlen genau die Elemente, die im Mockup den "Spiel"-Eindruck erzeugen: eine **zentrale Bühne** (die Insel selbst als Bild, nicht als Textliste), **Gesichter** (Avatare statt Namens-Buchstaben) und **gemalte Icons** statt Emoji/Text. Das Layout müsste dafür auch auf Sidebar + Bühne + Panel umgestellt werden (separater Schritt, sobald Assets da sind).

## Prioritäts-Tier 1 – groesster visueller Effekt, kleinste Stueckzahl

### 1. Insel-Hintergrundszene
- **Wofuer:** zentrale "Buehne" des Dashboards, ersetzt die aktuelle Fortschrittsleiste/Kostenliste als reinen Text
- **Format:** 1 Bild, PNG oder JPG, ca. 1600×1000px (bleibt als `background-image` skalierbar), warme Draufsicht/Isometrie wie im Mockup
- **Inhalt:** Strand, Wasser, ein paar Baeume/Felsen, Platz fuer 1 Gebaeude-Icon (Strandhuette) an einer markierten Stelle. Muss nicht die finale Vollversion mit allen 6 Gebaeuden zeigen – fuer den MVP reicht eine Szene mit **einem** klar erkennbaren Bauplatz.
- **Beispiel-Prompt fuer Bildgenerierung:**
  > "Cute isometric cartoon illustration of a small tropical desert island, top-down 3/4 view, sandy beach, turquoise water, a few palm trees and rocks, one empty flat building plot marked with a small wooden sign, warm soft lighting, children's game art style, no text, clean background"

### 2. Rohstoff-Icon-Set (5 Icons)
- **Wofuer:** ersetzt die aktuellen Emoji (🪵⚙️🧵🪢⭐) 1:1 in `ResourceBar`, `TaskCard`, `BuildingProgress`
- **Format:** 5× PNG mit Transparenz, je ca. 64×64px, gleicher Bildstil/gleiche Kantenlinie fuer alle 5
- **Inhalt:** Holz (Holzscheit-Stapel), Metall (Schraubenschluessel oder Metallteil), Stoff (Stoffballen/Garnrolle), Seil (aufgerolltes Seil), Sterne (Stern) – exakt wie im Mockup oben rechts zu sehen
- **Beispiel-Prompt:**
  > "Set of 5 flat cartoon game icons on transparent background: wood logs, metal gear, fabric roll, coiled rope, gold star. Consistent style, soft shadow, rounded shapes, warm color palette, no text, icon sheet"

### 3. Familien-Avatare (5 Portraits)
- **Wofuer:** ersetzt die aktuellen Buchstaben-Kreise in der Profilauswahl und im Header
- **Format:** 5× PNG mit Transparenz, rund zugeschnitten oder freigestellt, je ca. 200×200px
- **Inhalt:** Manuel, Kathrin (Erwachsene), Emil (5), Thea (7), Nova (8) – unterscheidbar durch Haarfarbe/Kleidung, gleicher Illustrationsstil wie im Mockup ("Papa", "Mama", "Lena", "Mia", "Emil" dort als Platzhalternamen zu sehen)
- **Beispiel-Prompt (pro Person, hier Beispiel Kind):**
  > "Cute cartoon character portrait, round headshot, young boy age 5, brown hair, friendly smile, flat illustration style, pastel background circle, children's game avatar, no text"

## Prioritäts-Tier 2 – deutliche Zusatz-Politur

### 4. Gebaeude-Icon "Strandhuette" (Baufortschritt)
- **Alternative A (guenstiger):** 1 Icon der fertigen Huette + eine generische "Baustelle"-Variante (Geruest), Fortschritt wird weiterhin per Balken/Prozent gezeigt, nicht per Bildwechsel
- **Alternative B (naeher am Mockup):** 3 Varianten (Bauplatz/leer, im Bau mit Geruest, fertig), passend zur 5-stufigen Baustufen-Logik im Code (aktuell: Bauplatz/Fundament/Waende/Dach/Fertig – 3 Bilder reichen, mittlere Stufen zeigen die "im Bau"-Variante)
- **Format:** PNG mit Transparenz, ca. 300×300px, gleicher Stil wie die Insel-Szene, damit es zur Hintergrundszene passt

### 5. Aufgaben-Icons (kleines Set, wiederverwendbar)
- **Wofuer:** kleines Icon links neben jedem Aufgabentitel in `TaskCard` (im Mockup: Spielzeugkiste, Tisch mit Geschirr, Waeschekorb)
- **Format:** ca. 10 PNG-Icons, 48×48px, transparent, gleicher Stil wie Rohstoff-Icons
- **Inhalt-Vorschlaege (an die Aufgabenvorlagen aus der Spec angelehnt):** Zimmer aufraeumen, Spielzeug einsammeln, Tisch decken/abraeumen, Waesche sortieren, Muell wegbringen, Pflanzen giessen, Schulranzen packen, Kochen helfen, Schuhe wegraeumen, Werkzeug sortieren
- Kann auch schrittweise nachgeliefert werden – Start mit den 5 Icons, die die Demo-Aufgaben tatsaechlich brauchen

### 6. Minispiel-Kartenbilder
- **Wofuer:** Vorschaubild auf der Schatzsuche-Karte (aktuell nur Text "Schatzsuche am Strand")
- **Format:** 1× PNG/JPG ca. 400×250px fuer Schatzsuche jetzt, weitere folgen mit kuenftigen Minispielen
- **Inhalt:** Schatzkarte mit rotem X, passend zum Mockup ("Schatzsuche" Karte mit Kompass/Schatztruhe-Motiv)

## Prioritäts-Tier 3 – spaeter, nicht MVP-kritisch

- Zusaetzliche Gebaeude-Illustrationen (Werkstatt, Garten, Tierstation, Bootssteg) fuer Phasen nach dem aktuellen MVP
- Weitere Minispiel-Kartenbilder (Papagei-Fangen, Bootsrennen)
- Deko-Elemente fuer die Insel-Szene (Wolken, Wellen-Textur, Lagerfeuer-Icon)
- UI-Chrome: Holzbrett-Textur fuer Buttons, Pergament-Textur fuer Panels (rein dekorativ, aktuell durch einfache abgerundete Karten ersetzt)

## Wie die Assets sourcen?

Drei realistische Wege, keiner davon erfordert, dass ich das selbst generiere:
1. **KI-Bildgenerierung** (Midjourney, DALL-E, o. ae.) mit den obigen Beispiel-Prompts als Startpunkt – am ehesten fuer Tier 1 geeignet (Insel-Szene, Icons), bei Avataren ggf. mehrere Versuche fuer konsistenten Stil noetig
2. **Fertige Asset-Packs** (z. B. itch.io, Kenney.nl, CraftPix) im passenden Cartoon-/Isometrie-Stil – oft guenstiger und stilistisch konsistent, aber weniger exakt auf die eigene Insel-Idee zugeschnitten
3. **Freelance-Illustrator** (Fiverr, Upwork) mit den beiden Referenzbildern als Briefing – teurer, aber ein Satz mit garantiert einheitlichem Stil

## Integration in den Code (spaeter, wenn Assets da sind)

Kurzer Ausblick, keine Aufgabe fuer jetzt: Assets kaemen nach `frontend/src/assets/`, zentral referenziert (z. B. `assets/resources/wood.png`) statt der aktuellen `resourceIcons.ts`-Emoji-Map. Das Dashboard-Layout muesste dafuer von der aktuellen einspaltigen Liste auf ein Grid mit Sidebar (Familie) + zentraler Insel-Karte + Aufgaben-Panel umgestellt werden – ein eigener Arbeitsblock, sobald du sagst "jetzt".
