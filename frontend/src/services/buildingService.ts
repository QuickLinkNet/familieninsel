import { api } from './api';
import type { Building } from '../types/building';

export async function fetchBuildings(): Promise<Building[]> {
  const { buildings } = await api.get<{ buildings: Building[] }>('/buildings');
  return buildings;
}

export async function contributeToBuilding(
  buildingId: number,
  amounts: Record<string, number>,
): Promise<{ justCompleted: boolean }> {
  return api.post(`/buildings/${buildingId}/contribute`, { amounts });
}
