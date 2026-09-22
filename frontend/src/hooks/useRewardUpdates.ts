import { useCallback, useEffect, useState } from 'react';
import type { RewardUpdates } from '../types/reward';
import { fetchRewardUpdates } from '../services/rewardService';

const EMPTY: RewardUpdates = { hasUpdates: false, events: [] };

export function useRewardUpdates(playerId: number | null) {
  const [updates, setUpdates] = useState<RewardUpdates>(EMPTY);

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
    void refresh();
  }, [refresh]);

  return { updates, refresh };
}
