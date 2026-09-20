import { useCallback, useEffect } from 'react';

const LANGUAGE = 'de-DE';

const isSupported = typeof window !== 'undefined' && 'speechSynthesis' in window;

export function useSpeechSynthesis() {
  useEffect(() => {
    return () => {
      if (isSupported) {
        window.speechSynthesis.cancel();
      }
    };
  }, []);

  const speak = useCallback((text: string) => {
    if (!isSupported) {
      return;
    }
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = LANGUAGE;
    window.speechSynthesis.speak(utterance);
  }, []);

  const stop = useCallback(() => {
    if (isSupported) {
      window.speechSynthesis.cancel();
    }
  }, []);

  return { speak, stop, isSupported };
}
