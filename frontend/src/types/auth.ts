export type PlayerRole = 'parent' | 'child';

export interface Player {
  id: number;
  name: string;
  age: number | null;
  role: PlayerRole;
  avatarKey: string;
}

export interface Family {
  id: number;
  name: string;
}

export interface SessionState {
  authenticated: boolean;
  familyId: number | null;
  playerId: number | null;
  playerRole: PlayerRole | null;
  parentUnlocked: boolean;
  csrfToken: string;
}
