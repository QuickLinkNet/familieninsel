export type PlayerRole = 'parent' | 'child';

export interface Player {
  id: number;
  name: string;
  age: number | null;
  role: PlayerRole;
  avatarKey: string;
  hasPhoto: boolean;
}

export interface ParentCandidate {
  id: number;
  name: string;
  age: number | null;
  role: PlayerRole;
  avatarKey: string;
}

export interface ManagedPlayer extends Player {
  isActive: boolean;
}

export interface SessionState {
  authenticated: boolean;
  familyId: number | null;
  playerId: number | null;
  playerRole: PlayerRole | null;
  csrfToken: string;
}
