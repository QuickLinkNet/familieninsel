import { useEffect, useRef, useState } from 'react';
import { useSpeechSynthesis } from './useSpeechSynthesis';
import islandMap from '../../assets/island/insel-karte.webp';
import parrotIcon from '../../assets/island/icon-papagei.webp';
import stormIcon from '../../assets/island/story-sturm.webp';
import beachHutFinished from '../../assets/island/strandhuette-5-fertig.webp';
import './onboarding.css';

interface Slide {
  image: string;
  text: string;
}

const SLIDES: Slide[] = [
  { image: stormIcon, text: 'Ein wilder Sturm hat euer Boot erwischt!' },
  {
    image: islandMap,
    text: 'Aber alle sind wohlauf! Ihr seid an einem geheimnisvollen Strand gestrandet.',
  },
  { image: parrotIcon, text: 'Hallo! Ich bin Pico. Ich zeige dir, was wir tun müssen!' },
  {
    image: beachHutFinished,
    text: 'Zusammen bauen wir eine Strandhütte! Dafür brauchen wir Holz, Metall, Stoff und Seile.',
  },
];

const TRANSITION_MS = 250;

interface IntroStoryProps {
  onFinished: () => void;
}

export function IntroStory({ onFinished }: IntroStoryProps) {
  const [slideIndex, setSlideIndex] = useState(0);
  const [leaving, setLeaving] = useState(false);
  const { speak, isSupported } = useSpeechSynthesis();
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (timer.current !== null) {
        clearTimeout(timer.current);
      }
    };
  }, []);

  const isLastSlide = slideIndex === SLIDES.length - 1;
  const slide = SLIDES[slideIndex];

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

  return (
    <div className="intro-story">
      <div className={`intro-story__panel${leaving ? ' intro-story__panel--leaving' : ''}`}>
        <img src={slide.image} alt="" aria-hidden="true" className="intro-story__image" />
        <p className="intro-story__text">{slide.text}</p>
        <div className="intro-story__actions">
          {isSupported && (
            <button
              type="button"
              className="intro-story__speak-button"
              onClick={() => {
                speak(slide.text);
              }}
            >
              🔊 Vorlesen
            </button>
          )}
          <button type="button" className="intro-story__next-button" onClick={goNext}>
            {isLastSlide ? 'Los geht’s!' : 'Weiter'}
          </button>
        </div>
        <div className="intro-story__dots">
          {SLIDES.map((_, index) => (
            <span
              key={index}
              className={`intro-story__dot${index === slideIndex ? ' intro-story__dot--active' : ''}`}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
