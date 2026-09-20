import { useEffect, useState } from 'react';
import parrotIcon from '../../assets/island/icon-papagei.webp';
import './onboarding.css';

interface Rect {
  top: number;
  left: number;
  width: number;
  height: number;
}

interface OnboardingSpotlightProps {
  targetSelector: string;
  message: string;
  onDismiss: () => void;
}

const PADDING = 8;
const BUBBLE_WIDTH = 280;
const BUBBLE_MARGIN = 16;

export function OnboardingSpotlight({ targetSelector, message, onDismiss }: OnboardingSpotlightProps) {
  const [rect, setRect] = useState<Rect | null>(null);

  useEffect(() => {
    function measure(): void {
      const target = document.querySelector(targetSelector);
      if (target === null) {
        setRect(null);
        return;
      }
      const bounds = target.getBoundingClientRect();
      setRect({
        top: bounds.top - PADDING,
        left: bounds.left - PADDING,
        width: bounds.width + PADDING * 2,
        height: bounds.height + PADDING * 2,
      });
    }

    measure();
    const raf = requestAnimationFrame(measure);
    window.addEventListener('resize', measure);
    window.addEventListener('scroll', measure, true);
    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener('resize', measure);
      window.removeEventListener('scroll', measure, true);
    };
  }, [targetSelector]);

  if (rect === null) {
    return null;
  }

  const bubbleLeft = Math.min(Math.max(rect.left, BUBBLE_MARGIN), window.innerWidth - BUBBLE_WIDTH - BUBBLE_MARGIN);
  const bubbleTop = rect.top + rect.height + BUBBLE_MARGIN;

  return (
    <div className="onboarding-spotlight">
      <div
        className="onboarding-spotlight__cutout"
        style={{ top: rect.top, left: rect.left, width: rect.width, height: rect.height }}
      />
      <div
        className="onboarding-spotlight__bubble"
        style={{ top: bubbleTop, left: bubbleLeft, width: BUBBLE_WIDTH }}
      >
        <img src={parrotIcon} alt="" aria-hidden="true" className="onboarding-spotlight__parrot" />
        <p>{message}</p>
        <button type="button" onClick={onDismiss}>
          Alles klar!
        </button>
      </div>
    </div>
  );
}
