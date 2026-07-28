export type TaskStatus = 'open' | 'completed_pending' | 'approved' | 'rejected' | 'cancelled';

export interface TaskReward {
  resourceId: number;
  amount: number;
}

export interface Task {
  id: number;
  title: string;
  description: string | null;
  status: TaskStatus;
  assignedPlayerId: number;
  createdByPlayerId: number;
  dueDate: string | null;
  parentNote: string | null;
  completedAt: string | null;
  approvedAt: string | null;
  createdAt: string;
  rewards: TaskReward[];
}
