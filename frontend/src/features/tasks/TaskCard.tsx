import type { ReactNode } from 'react';
import type { Task, TaskStatus } from '../../types/task';
import type { Resource } from '../../types/resource';
import { ResourceIcon } from '../resources/ResourceIcon';

const STATUS_LABELS: Record<TaskStatus, string> = {
  open: 'Offen',
  completed_pending: 'Wartet auf Bestätigung',
  approved: 'Bestätigt',
  rejected: 'Abgelehnt',
  cancelled: 'Gelöscht',
};

function RewardList({ task, resources }: { task: Task; resources: Resource[] }) {
  const rewards = task.rewards.filter((reward) => reward.amount > 0);

  return (
    <>
      {rewards.map((reward) => {
        const resource = resources.find((candidate) => candidate.id === reward.resourceId);
        return (
          <span key={reward.resourceId} className="task-reward__item">
            <ResourceIcon resourceKey={resource?.key ?? ''} className="task-reward__icon" />
            {reward.amount} {resource?.name ?? '?'}
          </span>
        );
      })}
    </>
  );
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
      <p className="task-reward">
        Belohnung: <RewardList task={task} resources={resources} />
      </p>
      {task.status === 'rejected' && task.parentNote !== null && task.parentNote !== '' && (
        <p className="task-parent-note">Notiz: {task.parentNote}</p>
      )}
      {children}
    </div>
  );
}
