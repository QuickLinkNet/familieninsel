import { useEffect, useRef, useState } from 'react';
import type { ReactZoomPanPinchRef } from 'react-zoom-pan-pinch';
import { TransformWrapper, TransformComponent } from 'react-zoom-pan-pinch';
import type { Building } from '../../types/building';
import islandMap from '../../assets/island/insel-karte.webp';
import { BuildingSprite } from './BuildingSprite';
import './island-map.css';

interface IslandMapProps {
  buildings: Building[];
}

const BUILDING_PLOTS: Record<string, { left: number; top: number; width: number }> = {
  beach_hut: { left: 49, top: 53, width: 17 },
  watchtower: { left: 70, top: 24, width: 14 },
};

// Feste Canvas-Groesse der insel-karte.png - alle Prozent-Positionen (z. B. bei
// BUILDING_PLOTS) beziehen sich auf dieses Koordinatensystem, nicht auf den Viewport.
const MAP_NATURAL_WIDTH = 1536;
const MAP_NATURAL_HEIGHT = 1024;

interface FitScales {
  containScale: number;
  coverScale: number;
}

function computeFitScales(width: number, height: number): FitScales {
  return {
    containScale: Math.min(width / MAP_NATURAL_WIDTH, height / MAP_NATURAL_HEIGHT),
    coverScale: Math.max(width / MAP_NATURAL_WIDTH, height / MAP_NATURAL_HEIGHT),
  };
}

export function IslandMap({ buildings }: IslandMapProps) {
  const transformRef = useRef<ReactZoomPanPinchRef | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const [fitScales, setFitScales] = useState(() => computeFitScales(window.innerWidth, window.innerHeight));

  useEffect(() => {
    const container = containerRef.current;
    if (container === null) {
      return;
    }

    const observer = new ResizeObserver((entries) => {
      const entry = entries[0];
      if (entry === undefined) {
        return;
      }
      const { width, height } = entry.contentRect;
      const nextFitScales = computeFitScales(width, height);
      setFitScales(nextFitScales);
      transformRef.current?.centerView(nextFitScales.coverScale, 0);
    });

    observer.observe(container);
    return () => observer.disconnect();
  }, []);

  return (
    <div className="island-map" ref={containerRef}>
      <TransformWrapper
        ref={transformRef}
        initialScale={fitScales.coverScale}
        minScale={fitScales.containScale}
        maxScale={fitScales.coverScale * 5}
        centerOnInit
        doubleClick={{ mode: 'toggle' }}
      >
        <TransformComponent
          wrapperClass="island-map__wrapper"
          contentClass="island-map__content"
          wrapperStyle={{ width: '100%', height: '100%' }}
        >
          <img
            src={islandMap}
            alt="Insel-Karte"
            className="island-map__image"
            width={MAP_NATURAL_WIDTH}
            height={MAP_NATURAL_HEIGHT}
            draggable={false}
          />
          {buildings.map((building) => {
            const plot = BUILDING_PLOTS[building.key];
            return plot === undefined ? null : (
              <BuildingSprite key={building.id} building={building} plot={plot} />
            );
          })}
        </TransformComponent>
      </TransformWrapper>
    </div>
  );
}
