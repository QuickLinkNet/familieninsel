import { api } from './api';
import type { Minigame } from '../types/minigame';

export async function fetchMinigames(): Promise<Minigame[]> {
  const { minigames } = await api.get<{ minigames: Minigame[] }>('/minigames');
  return minigames;
}

export async function completeMinigame(key: string): Promise<{ starsAwarded: number }> {
  return api.post(`/minigames/${key}/complete`);
}
