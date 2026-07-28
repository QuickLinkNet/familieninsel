import { useState } from 'react';
import { completeMinigame } from '../../services/minigameService';
import { ApiError } from '../../types/api';

interface HiddenItem {
  id: string;
  emoji: string;
  label: string;
  top: string;
  left: string;
}

const ITEMS: HiddenItem[] = [
  { id: 'shell', emoji: '🐚', label: 'Muschel', top: '20%', left: '12%' },
  { id: 'starfish', emoji: '⭐', label: 'Seestern', top: '58%', left: '78%' },
  { id: 'bottle', emoji: '🍾', label: 'Flaschenpost', top: '72%', left: '28%' },
  { id: 'compass', emoji: '🧭', label: 'Kompass', top: '28%', left: '62%' },
  { id: 'key', emoji: '🔑', label: 'Schlüssel', top: '12%', left: '82%' },
];

interface TreasureHuntGameProps {
  onCompleted: (starsAwarded: number) => void;
}

export function TreasureHuntGame({ onCompleted }: TreasureHuntGameProps) {
  const [found, setFound] = useState<Set<string>>(new Set());
  const [showHint, setShowHint] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleFind(id: string): Promise<void> {
    if (found.has(id) || submitting) {
      return;
    }

    const next = new Set(found);
    next.add(id);
    setFound(next);

    if (next.size === ITEMS.length) {
      setSubmitting(true);
      setError(null);
      try {
        const result = await completeMinigame('schatzsuche');
        onCompleted(result.starsAwarded);
      } catch (err) {
        setError(err instanceof ApiError ? err.message : 'Fehler beim Abschließen.');
      } finally {
        setSubmitting(false);
      }
    }
  }

  return (
    <div className="treasure-hunt">
      <div className="treasure-hunt__toolbar">
        <p>
          {found.size}/{ITEMS.length} gefunden
        </p>
        <button
          type="button"
          onClick={() => setShowHint((previous) => !previous)}
        >
          {showHint ? 'Hilfe ausblenden' : 'Hilfe'}
        </button>
      </div>
      <div className="treasure-hunt__beach">
        {ITEMS.map((item) => {
          const isFound = found.has(item.id);

          return (
            <button
              key={item.id}
              type="button"
              className={`treasure-spot${isFound ? ' treasure-spot--found' : ''}${showHint && !isFound ? ' treasure-spot--hint' : ''}`}
              style={{ top: item.top, left: item.left }}
              onClick={() => {
                void handleFind(item.id);
              }}
              disabled={isFound}
              aria-label={isFound ? `${item.label} gefunden` : 'Verstecktes Objekt'}
            >
              {isFound ? item.emoji : '❓'}
            </button>
          );
        })}
      </div>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </div>
  );
}
