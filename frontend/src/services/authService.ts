import { api, setCsrfToken } from './api';
import type { ParentCandidate, Player, SessionState } from '../types/auth';

export async function fetchSession(): Promise<SessionState> {
  const session = await api.get<SessionState>('/auth/session');
  setCsrfToken(session.csrfToken);
  return session;
}

export async function fetchParentCandidates(): Promise<ParentCandidate[]> {
  const { players } = await api.get<{ players: ParentCandidate[] }>('/auth/parents');
  return players;
}

export async function parentLogin(playerId: number, pin: string): Promise<Player> {
  const { player } = await api.post<{ player: Player }>('/auth/parent-login', { playerId, pin });
  return player;
}

export async function qrLogin(token: string): Promise<Player> {
  const { player } = await api.post<{ player: Player }>('/auth/qr-login', { token });
  return player;
}

export async function fetchPlayers(): Promise<Player[]> {
  const { players } = await api.get<{ players: Player[] }>('/players');
  return players;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}
