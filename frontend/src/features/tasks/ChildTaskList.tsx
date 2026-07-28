import { useState } from 'react';
import type { Task } from '../../types/task';
import type { Resource } from '../../types/resource';
import { TaskCard } from './TaskCard';
import { completeTask } from '../../services/taskService';
import { ApiError } from '../../types/api';

interface ChildTaskListProps {
  tasks: Task[];
  resources: Resource[];
  onChanged: () => void;
}

export function ChildTaskList({ tasks, resources, onChanged }: ChildTaskListProps) {
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleComplete(taskId: number): Promise<void> {
    setPendingId(taskId);
    setError(null);
    try {
      await completeTask(taskId);
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Fehler beim Melden.');
    } finally {
      setPendingId(null);
    }
  }

  if (tasks.length === 0) {
    return (
      <p className="empty-state">
        <span aria-hidden="true">🎉</span> Keine Aufgaben für dich gerade!
      </p>
    );
  }

  return (
    <div className="task-list">
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
      {tasks.map((task) => (
        <TaskCard key={task.id} task={task} resources={resources}>
          {task.status === 'open' && (
            <button
              type="button"
              className="task-complete-button"
              disabled={pendingId === task.id}
              onClick={() => {
                void handleComplete(task.id);
              }}
            >
              <span aria-hidden="true">✅</span> Erledigt!
            </button>
          )}
        </TaskCard>
      ))}
    </div>
  );
}
