import { useCallback, useEffect, useState } from 'react';
import type { Minigame } from '../types/minigame';
import { ApiError } from '../types/api';
import { fetchMinigames } from '../services/minigameService';

export function useMinigames() {
  const [minigames, setMinigames] = useState<Minigame[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setError(null);
    try {
      setMinigames(await fetchMinigames());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Verbindung zum Server fehlgeschlagen.');
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    refresh()
      .catch(() => undefined)
      .finally(() => setLoading(false));
  }, [refresh]);

  return { minigames, loading, error, refresh };
}
