import { api } from './api';
import { ApiError } from '../types/api';
import type { Building } from '../types/building';

export async function fetchActiveBuilding(): Promise<Building | null> {
  try {
    const { building } = await api.get<{ building: Building }>('/buildings/active');
    return building;
  } catch (err) {
    if (err instanceof ApiError && err.code === 'BUILDING_NOT_FOUND') {
      return null;
    }
    throw err;
  }
}

export async function contributeToBuilding(
  buildingId: number,
  amounts: Record<string, number>,
): Promise<{ justCompleted: boolean }> {
  return api.post(`/buildings/${buildingId}/contribute`, { amounts });
}
