import { useState } from 'react';
import type { Minigame } from '../../types/minigame';
import { TreasureHuntGame } from './TreasureHuntGame';

interface MinigameSectionProps {
  minigames: Minigame[];
  onChanged: () => void;
}

export function MinigameSection({ minigames, onChanged }: MinigameSectionProps) {
  const [celebration, setCelebration] = useState<number | null>(null);
  const [playing, setPlaying] = useState(false);

  const schatzsuche = minigames.find((minigame) => minigame.key === 'schatzsuche');
  if (schatzsuche === undefined) {
    return null;
  }

  function handleCompleted(starsAwarded: number): void {
    setCelebration(starsAwarded);
    setPlaying(false);
    onChanged();
  }

  return (
    <section className="minigame-section">
      <h2>Minispiele</h2>
      {!schatzsuche.unlocked ? (
        <div className="minigame-card minigame-card--locked">
          <p>{schatzsuche.name}</p>
          <p className="minigame-locked-hint">
            Noch gesperrt – schaltet sie frei, indem ihr ein Gebäude fertigstellt.
          </p>
        </div>
      ) : (
        <div className="minigame-card">
          <p>{schatzsuche.name}</p>
          {celebration !== null && (
            <p className="building-complete" role="status">
              {celebration > 0 ? `Super! Du hast ${celebration} Sterne bekommen!` : 'Nochmal geschafft!'}
            </p>
          )}
          {playing ? (
            <TreasureHuntGame onCompleted={handleCompleted} />
          ) : (
            <button
              type="button"
              onClick={() => {
                setPlaying(true);
                setCelebration(null);
              }}
            >
              {schatzsuche.firstCompletedAt !== null ? 'Nochmal spielen' : 'Spielen'}
            </button>
          )}
        </div>
      )}
    </section>
  );
}
