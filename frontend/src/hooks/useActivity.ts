import { useCallback, useEffect, useState } from 'react';
import type { ActivityEntry } from '../types/activity';
import { fetchActivity } from '../services/activityService';

export function useActivity() {
  const [entries, setEntries] = useState<ActivityEntry[]>([]);

  const refresh = useCallback(async () => {
    try {
      setEntries(await fetchActivity());
    } catch {
      // Tagebuch ist nicht kritisch fuer den Hauptablauf - Fehler hier bewusst nicht blockierend.
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return { entries, refresh };
}
