import { api } from './api';

export async function resetIntro(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/reset-intro`);
}

export async function resetRewards(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/reset-rewards`);
}

export async function resetTasks(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/reset-tasks`);
}

export async function resetFamilyProgress(): Promise<void> {
  await api.post('/family/reset-progress');
}
