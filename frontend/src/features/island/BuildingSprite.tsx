import type { Building } from '../../types/building';
import bauplatz from '../../assets/island/strandhuette-1-bauplatz.webp';
import fundament from '../../assets/island/strandhuette-2-fundament.webp';
import rohbau from '../../assets/island/strandhuette-3-rohbau.webp';
import dach from '../../assets/island/strandhuette-4-dach.webp';
import fertig from '../../assets/island/strandhuette-5-fertig.webp';
import wachturmBauplatz from '../../assets/island/wachturm-1-bauplatz.webp';
import wachturmFundament from '../../assets/island/wachturm-2-fundament.webp';
import wachturmGeruest from '../../assets/island/wachturm-3-geruest.webp';
import wachturmPlattform from '../../assets/island/wachturm-4-plattform.webp';
import wachturmFertig from '../../assets/island/wachturm-5-fertig.webp';

interface StageImage {
  src: string;
  // Position des tatsaechlichen Bildinhalts (nicht der vollen, teils
  // transparenten Leinwand) als Prozent der Bildhoehe/-breite. Jede
  // KI-generierte Baustufe hat unterschiedlich viel transparenten Rand -
  // ohne diese Kalibrierung "schwebt" das Gebaeude je nach Baustufe
  // unterschiedlich hoch ueber dem Bauplatz.
  centerXPct: number;
  bottomPct: number;
}

// Baustufen-Grafiken je Gebaeude-Key. Gebaeude ohne eigenen Eintrag hier
// zeigen als Platzhalter den generischen Bauplatz (siehe STAGE_IMAGES-Zugriff
// unten), bis eigene Baustufen-Assets generiert wurden.
const STAGE_IMAGES: Record<string, Record<number, StageImage>> = {
  beach_hut: {
    1: { src: bauplatz, centerXPct: 49.5, bottomPct: 83.0 },
    2: { src: fundament, centerXPct: 51.2, bottomPct: 84.8 },
    3: { src: rohbau, centerXPct: 49.1, bottomPct: 87.8 },
    4: { src: dach, centerXPct: 47.6, bottomPct: 83.7 },
    5: { src: fertig, centerXPct: 50.5, bottomPct: 91.9 },
  },
  watchtower: {
    1: { src: wachturmBauplatz, centerXPct: 49.7, bottomPct: 83.7 },
    2: { src: wachturmFundament, centerXPct: 50.7, bottomPct: 85.4 },
    3: { src: wachturmGeruest, centerXPct: 48.2, bottomPct: 97.8 },
    4: { src: wachturmPlattform, centerXPct: 42.0, bottomPct: 87.2 },
    5: { src: wachturmFertig, centerXPct: 50.0, bottomPct: 94.5 },
  },
};

const FALLBACK_STAGE_IMAGE: StageImage = { src: bauplatz, centerXPct: 49.5, bottomPct: 83.0 };

/**
 * Nur das Bild einer Baustufe, ohne die Positionierungs-Kalibrierung fuer
 * die Insel-Karte - fuer Stellen wie RewardReveal, die das Gebaeude
 * freistehend (nicht auf der Karte platziert) zeigen wollen.
 */
export function stageImageSrc(buildingKey: string, stage: number): string {
  return (STAGE_IMAGES[buildingKey]?.[stage] ?? FALLBACK_STAGE_IMAGE).src;
}

interface BuildingSpritePlot {
  left: number;
  top: number;
  width: number;
}

interface BuildingSpriteProps {
  building: Building;
  plot: BuildingSpritePlot;
}

export function BuildingSprite({ building, plot }: BuildingSpriteProps) {
  const stageImage = STAGE_IMAGES[building.key]?.[building.stage] ?? FALLBACK_STAGE_IMAGE;

  return (
    <img
      src={stageImage.src}
      alt={`${building.name} - Baustufe ${building.stage}`}
      className="building-sprite"
      draggable={false}
      style={{
        left: `${plot.left}%`,
        top: `${plot.top}%`,
        width: `${plot.width}%`,
        transform: `translate(-${stageImage.centerXPct}%, -${stageImage.bottomPct}%)`,
      }}
    />
  );
}
