import { useCallback, useEffect, useState } from 'react';
import type { Building } from '../types/building';
import { ApiError } from '../types/api';
import { fetchBuildings } from '../services/buildingService';

export function useBuildings() {
  const [buildings, setBuildings] = useState<Building[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setError(null);
    try {
      setBuildings(await fetchBuildings());
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

  return { buildings, loading, error, refresh };
}
