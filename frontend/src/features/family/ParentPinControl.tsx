import { useState } from 'react';
import * as playerService from '../../services/playerService';
import { PinDots } from '../auth/PinDots';
import { PinKeypad } from '../auth/PinKeypad';

interface ParentPinControlProps {
  playerId: number;
}

const PIN_LENGTH = 4;

type Step = 'closed' | 'first' | 'confirm';

export function ParentPinControl({ playerId }: ParentPinControlProps) {
  const [step, setStep] = useState<Step>('closed');
  const [firstPin, setFirstPin] = useState('');
  const [pin, setPin] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  function startOver(message: string | null): void {
    setStep('first');
    setFirstPin('');
    setPin('');
    setError(message);
  }

  async function handleDigit(digit: string): Promise<void> {
    if (submitting || pin.length >= PIN_LENGTH) {
      return;
    }
    const nextPin = pin + digit;
    setPin(nextPin);

    if (nextPin.length !== PIN_LENGTH) {
      return;
    }

    if (step === 'first') {
      setFirstPin(nextPin);
      setPin('');
      setStep('confirm');
      return;
    }

    if (nextPin !== firstPin) {
      startOver('Die PINs stimmen nicht überein. Nochmal von vorn.');
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      await playerService.setParentPin(playerId, nextPin);
      setStep('closed');
      setFirstPin('');
      setPin('');
      setSuccess(true);
    } catch {
      startOver('PIN konnte nicht gesetzt werden.');
    } finally {
      setSubmitting(false);
    }
  }

  function removeLastDigit(): void {
    if (!submitting) {
      setPin((current) => current.slice(0, -1));
    }
  }

  function resetPin(): void {
    if (!submitting) {
      setPin('');
    }
  }

  if (step === 'closed') {
    return (
      <div className="parent-pin-control">
        <button
          type="button"
          onClick={() => {
            setSuccess(false);
            startOver(null);
          }}
        >
          Neue PIN setzen
        </button>
        {success && <span className="parent-pin-control__success"> PIN wurde geändert.</span>}
      </div>
    );
  }

  return (
    <div className="parent-pin-control parent-pin-control--open">
      <p className="parent-pin-control__hint">
        {step === 'first' ? 'Neue 4-stellige PIN eingeben' : 'PIN zur Bestätigung wiederholen'}
      </p>
      <PinDots length={PIN_LENGTH} filledCount={pin.length} />
      <PinKeypad onDigit={(digit) => void handleDigit(digit)} onBackspace={removeLastDigit} onReset={resetPin} disabled={submitting} />
      <button
        type="button"
        className="auth-form__secondary"
        onClick={() => {
          setStep('closed');
          setFirstPin('');
          setPin('');
          setError(null);
        }}
        disabled={submitting}
      >
        Abbrechen
      </button>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </div>
  );
}
