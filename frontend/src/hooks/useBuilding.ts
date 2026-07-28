import { useCallback, useEffect, useState } from 'react';
import type { Building } from '../types/building';
import { ApiError } from '../types/api';
import { fetchActiveBuilding } from '../services/buildingService';

export function useBuilding() {
  const [building, setBuilding] = useState<Building | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setError(null);
    try {
      setBuilding(await fetchActiveBuilding());
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

  return { building, loading, error, refresh };
}
