import { api } from './api';
import type { RewardUpdates } from '../types/reward';

export async function fetchRewardUpdates(playerId: number): Promise<RewardUpdates> {
  return api.get<RewardUpdates>(`/players/${playerId}/reward-updates`);
}

export async function acknowledgeRewardUpdates(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/reward-updates/ack`);
}
