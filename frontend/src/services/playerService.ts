import { api } from './api';
import type { ManagedPlayer } from '../types/auth';

export interface LoginTokenStatus {
  active: boolean;
  createdAt: string | null;
  lastUsedAt: string | null;
}

export async function createChild(name: string, age: number | null): Promise<number> {
  const { playerId } = await api.post<{ playerId: number }>('/players', { name, age });
  return playerId;
}

export async function fetchAllPlayers(): Promise<ManagedPlayer[]> {
  const { players } = await api.get<{ players: ManagedPlayer[] }>('/players/manage');
  return players;
}

export async function updatePlayer(playerId: number, name: string, age: number | null): Promise<void> {
  await api.put(`/players/${playerId}`, { name, age });
}

export async function setParentPin(playerId: number, pin: string): Promise<void> {
  await api.post(`/players/${playerId}/pin`, { pin });
}

export async function markIntroSeen(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/intro-seen`);
}

export async function activatePlayer(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/activate`);
}

export async function deactivatePlayer(playerId: number): Promise<void> {
  await api.post(`/players/${playerId}/deactivate`);
}

export async function fetchLoginTokenStatus(playerId: number): Promise<LoginTokenStatus> {
  const { active, createdAt, lastUsedAt } = await api.get<LoginTokenStatus>(`/players/${playerId}/login-token`);
  return { active, createdAt, lastUsedAt };
}

export async function regenerateLoginToken(playerId: number): Promise<string> {
  const { token } = await api.post<{ token: string }>(`/players/${playerId}/login-token`);
  return token;
}

export async function revokeLoginToken(playerId: number): Promise<void> {
  await api.delete(`/players/${playerId}/login-token`);
}
