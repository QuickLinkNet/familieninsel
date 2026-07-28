export type BuildingStatus = 'in_progress' | 'completed';

export interface BuildingCost {
  resourceId: number;
  required: number;
  contributed: number;
}

export interface Building {
  id: number;
  familyBuildingId: number;
  key: string;
  name: string;
  description: string | null;
  status: BuildingStatus;
  stage: number;
  progressPercent: number;
  completedAt: string | null;
  unlockMinigameKey: string | null;
  costs: BuildingCost[];
}
