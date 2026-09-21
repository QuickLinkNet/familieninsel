import { useState } from 'react';
import * as resetService from '../../services/resetService';

interface ChildResetControlProps {
  playerId: number;
  childName: string;
}

type ResetKind = 'intro' | 'rewards' | 'tasks';

const ACTIONS: Record<ResetKind, { label: string; confirm: string; run: (id: number) => Promise<void> }> = {
  intro: {
    label: 'Intro erneut zeigen',
    confirm: 'zeigt beim nächsten Login wieder das Story-Intro mit Pico',
    run: resetService.resetIntro,
  },
  rewards: {
    label: 'Belohnungen erneut zeigen',
    confirm: 'zeigt beim nächsten Login alle bisherigen Aufgaben-Belohnungen noch einmal als RewardReveal',
    run: resetService.resetRewards,
  },
  tasks: {
    label: 'Aufgaben zurücksetzen',
    confirm:
      'setzt alle Aufgaben dieses Kindes zurück auf "offen" (bereits erzielter Baufortschritt bleibt erhalten)',
    run: resetService.resetTasks,
  },
};

export function ChildResetControl({ playerId, childName }: ChildResetControlProps) {
  const [pending, setPending] = useState<ResetKind | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<ResetKind | null>(null);

  async function handleClick(kind: ResetKind): Promise<void> {
    const action = ACTIONS[kind];
    if (!window.confirm(`${childName}: ${action.confirm}. Fortfahren?`)) {
      return;
    }

    setPending(kind);
    setError(null);
    setSuccess(null);
    try {
      await action.run(playerId);
      setSuccess(kind);
    } catch {
      setError('Zurücksetzen fehlgeschlagen.');
    } finally {
      setPending(null);
    }
  }

  return (
    <div className="child-reset-control">
      <p className="child-reset-control__hint">Zum Testen zurücksetzen:</p>
      <div className="child-reset-control__actions">
        {(Object.keys(ACTIONS) as ResetKind[]).map((kind) => (
          <button
            key={kind}
            type="button"
            className="auth-form__secondary"
            disabled={pending !== null}
            onClick={() => {
              void handleClick(kind);
            }}
          >
            {ACTIONS[kind].label}
          </button>
        ))}
      </div>
      {success !== null && <p className="child-reset-control__success">Erledigt.</p>}
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </div>
  );
}
