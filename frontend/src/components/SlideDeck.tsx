import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import './slide-deck.css';

const TRANSITION_MS = 250;

/**
 * Zustandsmaschine fuer eine mehrseitige "Story-Moment"-Sequenz (Slide,
 * Leave-Uebergang, Slide, ...) - gemeinsamer Kern fuer IntroStory und
 * RewardReveal, die beide dieselbe Uebergangslogik unabhaengig voneinander
 * gebraucht haetten.
 */
export function useSlideDeck(slideCount: number, onFinished: () => void) {
  const [slideIndex, setSlideIndex] = useState(0);
  const [leaving, setLeaving] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (timer.current !== null) {
        clearTimeout(timer.current);
      }
    };
  }, []);

  const isLastSlide = slideIndex === slideCount - 1;

  function goNext(): void {
    if (isLastSlide) {
      onFinished();
      return;
    }
    setLeaving(true);
    timer.current = setTimeout(() => {
      setSlideIndex((current) => current + 1);
      setLeaving(false);
    }, TRANSITION_MS);
  }

  return { slideIndex, leaving, isLastSlide, goNext };
}

interface SlideDeckShellProps {
  slideCount: number;
  slideIndex: number;
  leaving: boolean;
  isLastSlide: boolean;
  onNext: () => void;
  finishLabel: string;
  nextLabel?: string;
  extraAction?: ReactNode;
  children: ReactNode;
}

/**
 * Die gemeinsame Huelle (abgedunkelter Hintergrund, Holz-Panel mit
 * Leave-Uebergang, Weiter/Fertig-Button, Punkte-Anzeige) - der Inhalt jeder
 * einzelnen Seite bleibt beim jeweiligen Aufrufer (IntroStory, RewardReveal).
 */
export function SlideDeckShell({
  slideCount,
  slideIndex,
  leaving,
  isLastSlide,
  onNext,
  finishLabel,
  nextLabel = 'Weiter',
  extraAction,
  children,
}: SlideDeckShellProps) {
  return (
    <div className="slide-deck">
      <div className={`slide-deck__panel${leaving ? ' slide-deck__panel--leaving' : ''}`}>
        {children}
        <div className="slide-deck__actions">
          {extraAction}
          <button type="button" className="slide-deck__next-button" onClick={onNext}>
            {isLastSlide ? finishLabel : nextLabel}
          </button>
        </div>
        <div className="slide-deck__dots">
          {Array.from({ length: slideCount }, (_, index) => (
            <span
              key={index}
              className={`slide-deck__dot${index === slideIndex ? ' slide-deck__dot--active' : ''}`}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
