export interface Minigame {
  id: number;
  key: string;
  name: string;
  description: string | null;
  unlocked: boolean;
  firstCompletedAt: string | null;
}
