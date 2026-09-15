import { useEffect, useRef, useState } from 'react';
import { useAuth } from './AuthContext';
import { PlayerAvatar } from './PlayerAvatar';
import { PinDots } from './PinDots';
import { PinKeypad } from './PinKeypad';
import manuelIcon from '../../assets/island/parent-icon-manuel.webp';
import kathrinIcon from '../../assets/island/parent-icon-kathrin.webp';
import manuelAvatarIcon from '../../assets/island/parent-icon-manuel-avatar.webp';
import kathrinAvatarIcon from '../../assets/island/parent-icon-kathrin-avatar.webp';
import './parent-login.css';

// Handgefertigte Icons je Elternteil (Name + "Elternteil" sind bereits im Bild
// eingebrannt) - Fallback auf den generischen PlayerAvatar, falls mal ein
// Elternteil mit unbekanntem avatarKey dazukommt.
const PARENT_ICONS: Record<string, string> = {
  manuel: manuelIcon,
  kathrin: kathrinIcon,
};

// Nur das runde Medaillon ohne Holzschild-Namensschild (das steht im
// PIN-Panel schon als eigene Ueberschrift darunter) - fuer den kleinen
// Avatar oben im PIN-Panel.
const PARENT_AVATAR_ICONS: Record<string, string> = {
  manuel: manuelAvatarIcon,
  kathrin: kathrinAvatarIcon,
};

const PIN_LENGTH = 4;
const TRANSITION_MS = 250;
const ERROR_DISPLAY_MS = 600;
const SUCCESS_DISPLAY_MS = 900;

type Phase = 'select' | 'leavingToPin' | 'pin' | 'leavingToSelect' | 'success';

export function ParentLoginScreen() {
  const { parentCandidates, loadParentCandidates, verifyParentPin, completeLogin, error } = useAuth();
  const [phase, setPhase] = useState<Phase>('select');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [pin, setPin] = useState('');
  const [pinError, setPinError] = useState(false);
  const [statusText, setStatusText] = useState('Bitte gib deinen PIN ein');
  const [verifying, setVerifying] = useState(false);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  // Zusaetzlich zum React-State gehaltener, synchron aktueller PIN-Wert:
  // appendDigit kann bei sehr schnell aufeinanderfolgenden Tastendruecken
  // (z.B. Auto-Klicks/sehr schnelles Tippen) mehrfach ausgeloest werden,
  // bevor React den vorherigen setPin-Aufruf gerendert hat - ueber den Ref
  // bleibt der tatsaechliche Eingabestand immer korrekt, unabhaengig vom
  // React-Render-Zeitpunkt.
  const pinRef = useRef('');

  useEffect(() => {
    void loadParentCandidates();
  }, [loadParentCandidates]);

  useEffect(() => {
    return () => {
      timers.current.forEach(clearTimeout);
    };
  }, []);

  function after(ms: number, fn: () => void): void {
    timers.current.push(setTimeout(fn, ms));
  }

  const selectedParent = parentCandidates.find((candidate) => candidate.id === selectedId) ?? null;

  function selectParent(id: number): void {
    setSelectedId(id);
    setPhase('leavingToPin');
    after(TRANSITION_MS, () => {
      pinRef.current = '';
      setPin('');
      setPinError(false);
      setStatusText('Bitte gib deinen PIN ein');
      setPhase('pin');
    });
  }

  function goBackToSelect(): void {
    setPhase('leavingToSelect');
    after(TRANSITION_MS, () => {
      setSelectedId(null);
      pinRef.current = '';
      setPin('');
      setPinError(false);
      setPhase('select');
    });
  }

  async function submitPin(candidatePin: string): Promise<void> {
    if (selectedId === null || verifying) {
      return;
    }
    setVerifying(true);
    const success = await verifyParentPin(selectedId, candidatePin);
    setVerifying(false);

    if (success) {
      setStatusText(`Willkommen zurück, ${selectedParent?.name}!`);
      setPhase('success');
      after(SUCCESS_DISPLAY_MS, () => {
        void completeLogin();
      });
      return;
    }

    setPinError(true);
    setStatusText('Hmm… der PIN stimmt nicht.');
    after(ERROR_DISPLAY_MS, () => {
      pinRef.current = '';
      setPin('');
      setPinError(false);
      setStatusText('Bitte gib deinen PIN ein');
    });
  }

  function appendDigit(digit: string): void {
    if (verifying || pinError || pinRef.current.length >= PIN_LENGTH) {
      return;
    }
    pinRef.current += digit;
    setPin(pinRef.current);
    if (pinRef.current.length === PIN_LENGTH) {
      void submitPin(pinRef.current);
    }
  }

  function removeLastDigit(): void {
    if (verifying || pinError) {
      return;
    }
    pinRef.current = pinRef.current.slice(0, -1);
    setPin(pinRef.current);
  }

  function resetPin(): void {
    if (verifying || pinError) {
      return;
    }
    pinRef.current = '';
    setPin('');
  }

  useEffect(() => {
    if (phase !== 'pin') {
      return;
    }

    function handleKeyDown(event: KeyboardEvent): void {
      if (event.key >= '0' && event.key <= '9') {
        appendDigit(event.key);
      } else if (event.key === 'Backspace') {
        removeLastDigit();
      } else if (event.key === 'Escape') {
        goBackToSelect();
      }
    }

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase, pin, verifying, pinError]);

  if (phase === 'select' || phase === 'leavingToSelect') {
    return (
      <main className="auth-screen">
        <div className={`parent-picker${phase === 'leavingToSelect' ? ' parent-picker--leaving' : ''}`}>
          {parentCandidates.map((candidate) => {
            const customIcon = PARENT_ICONS[candidate.avatarKey];
            return (
              <button
                key={candidate.id}
                type="button"
                className="parent-picker__icon"
                onClick={() => selectParent(candidate.id)}
              >
                {customIcon !== undefined ? (
                  <img src={customIcon} alt={`${candidate.name} - Elternteil`} className="parent-picker__custom-icon" />
                ) : (
                  <>
                    <PlayerAvatar
                      playerId={candidate.id}
                      name={candidate.name}
                      role="parent"
                      hasPhoto={false}
                      size={128}
                    />
                    <span className="parent-picker__name">{candidate.name}</span>
                  </>
                )}
              </button>
            );
          })}
        </div>
        {error !== null && (
          <p role="alert" className="auth-error auth-error--overlay">
            {error}
          </p>
        )}
      </main>
    );
  }

  return (
    <main className="auth-screen">
      <div className={`auth-panel pin-panel${phase === 'leavingToPin' ? ' pin-panel--entering-from' : ''}`}>
        {phase !== 'success' && (
          <button type="button" className="pin-panel__back" onClick={goBackToSelect} aria-label="Zurück">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M15 5 L8 12 L15 19"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </button>
        )}

        {selectedParent !== null && (
          <>
            {PARENT_AVATAR_ICONS[selectedParent.avatarKey] !== undefined ? (
              <img
                src={PARENT_AVATAR_ICONS[selectedParent.avatarKey]}
                alt=""
                aria-hidden="true"
                className="pin-panel__avatar"
              />
            ) : (
              <PlayerAvatar
                playerId={selectedParent.id}
                name={selectedParent.name}
                role="parent"
                hasPhoto={false}
                size={96}
              />
            )}
          </>
        )}

        <h1 className="auth-panel__title pin-panel__name">{selectedParent?.name}</h1>
        <p className={`pin-panel__status${pinError ? ' pin-panel__status--error' : ''}`}>{statusText}</p>

        {phase === 'success' ? (
          <span className="pin-panel__success-check" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                d="M5 13 L10 18 L19 7"
                stroke="#3a2610"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </span>
        ) : (
          <PinDots length={PIN_LENGTH} filledCount={pin.length} error={pinError} />
        )}

        {phase !== 'success' && (
          <PinKeypad onDigit={appendDigit} onBackspace={removeLastDigit} onReset={resetPin} disabled={verifying || pinError} />
        )}
      </div>
    </main>
  );
}
