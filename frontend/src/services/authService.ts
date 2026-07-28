import { api, setCsrfToken } from './api';
import type { Family, Player, SessionState } from '../types/auth';

export async function fetchSession(): Promise<SessionState> {
  const session = await api.get<SessionState>('/auth/session');
  setCsrfToken(session.csrfToken);
  return session;
}

export async function familyLogin(familyCode: string): Promise<Family> {
  const { family } = await api.post<{ family: Family }>('/auth/family-login', { familyCode });
  return family;
}

export async function fetchPlayers(): Promise<Player[]> {
  const { players } = await api.get<{ players: Player[] }>('/players');
  return players;
}

export async function selectProfile(playerId: number): Promise<Player> {
  const { player } = await api.post<{ player: Player }>('/auth/select-profile', { playerId });
  return player;
}

export async function unlockParent(pin: string): Promise<void> {
  await api.post('/auth/parent-unlock', { pin });
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}
