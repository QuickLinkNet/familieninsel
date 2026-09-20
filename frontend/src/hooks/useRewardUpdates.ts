import { useCallback, useEffect, useState } from 'react';
import type { RewardUpdates } from '../types/reward';
import { fetchRewardUpdates } from '../services/rewardService';

const EMPTY: RewardUpdates = { hasUpdates: false, events: [] };

export function useRewardUpdates(playerId: number | null) {
  const [updates, setUpdates] = useState<RewardUpdates>(EMPTY);
  const [loading, setLoading] = useState(playerId !== null);

  const refresh = useCallback(async () => {
    if (playerId === null) {
      setUpdates(EMPTY);
      return;
    }
    try {
      setUpdates(await fetchRewardUpdates(playerId));
    } catch {
      setUpdates(EMPTY);
    }
  }, [playerId]);

  useEffect(() => {
    setLoading(true);
    refresh()
      .catch(() => undefined)
      .finally(() => setLoading(false));
  }, [refresh]);

  return { updates, loading, refresh };
}
