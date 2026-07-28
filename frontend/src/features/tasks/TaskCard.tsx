import type { ReactNode } from 'react';
import type { Task, TaskStatus } from '../../types/task';
import type { Resource } from '../../types/resource';
import { resourceIcon } from '../../utils/resourceIcons';

const STATUS_LABELS: Record<TaskStatus, string> = {
  open: 'Offen',
  completed_pending: 'Wartet auf Bestätigung',
  approved: 'Bestätigt',
  rejected: 'Abgelehnt',
  cancelled: 'Gelöscht',
};

function rewardLabel(task: Task, resources: Resource[]): string {
  return task.rewards
    .filter((reward) => reward.amount > 0)
    .map((reward) => {
      const resource = resources.find((candidate) => candidate.id === reward.resourceId);
      const icon = resource !== undefined ? resourceIcon(resource.key) : '❔';
      return `${icon} ${reward.amount} ${resource?.name ?? '?'}`;
    })
    .join('  ');
}

interface TaskCardProps {
  task: Task;
  resources: Resource[];
  children?: ReactNode;
}

export function TaskCard({ task, resources, children }: TaskCardProps) {
  return (
    <div className="task-card">
      <div className="task-card__header">
        <h3>{task.title}</h3>
        <span className={`task-status task-status--${task.status}`}>{STATUS_LABELS[task.status]}</span>
      </div>
      {task.description !== null && task.description !== '' && (
        <p className="task-description">{task.description}</p>
      )}
      <p className="task-reward">Belohnung: {rewardLabel(task, resources)}</p>
      {task.status === 'rejected' && task.parentNote !== null && task.parentNote !== '' && (
        <p className="task-parent-note">Notiz: {task.parentNote}</p>
      )}
      {children}
    </div>
  );
}
