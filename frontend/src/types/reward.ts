export interface RewardAmount {
  resourceKey: string;
  amount: number;
}

export interface RewardBuildingProgress {
  key: string;
  name: string;
  beforePercent: number;
  afterPercent: number;
  beforeStage: number;
  afterStage: number;
  justCompleted: boolean;
  unlockedMinigameName: string | null;
  unlockedBuildingName: string | null;
}

export interface RewardEvent {
  taskId: number;
  taskTitle: string;
  rewards: RewardAmount[];
  building: RewardBuildingProgress | null;
}

export interface RewardUpdates {
  hasUpdates: boolean;
  events: RewardEvent[];
}
