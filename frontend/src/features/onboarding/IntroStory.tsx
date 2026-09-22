import { useSlideDeck, SlideDeckShell } from '../../components/SlideDeck';
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

interface IntroStoryProps {
  onFinished: () => void;
}

export function IntroStory({ onFinished }: IntroStoryProps) {
  const { speak, isSupported } = useSpeechSynthesis();
  const { slideIndex, leaving, isLastSlide, goNext } = useSlideDeck(SLIDES.length, onFinished);
  const slide = SLIDES[slideIndex];

  return (
    <SlideDeckShell
      slideCount={SLIDES.length}
      slideIndex={slideIndex}
      leaving={leaving}
      isLastSlide={isLastSlide}
      onNext={goNext}
      finishLabel="Los geht’s!"
      extraAction={
        isSupported ? (
          <button
            type="button"
            className="intro-story__speak-button"
            onClick={() => {
              speak(slide.text);
            }}
          >
            🔊 Vorlesen
          </button>
        ) : undefined
      }
    >
      <img src={slide.image} alt="" aria-hidden="true" className="intro-story__image" />
      <p className="intro-story__text">{slide.text}</p>
    </SlideDeckShell>
  );
}
