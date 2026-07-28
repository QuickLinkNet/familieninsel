import { useState } from 'react';
import type { Task } from '../../types/task';
import type { Resource } from '../../types/resource';
import type { Player } from '../../types/auth';
import { TaskCard } from './TaskCard';
import { CreateTaskForm } from './CreateTaskForm';
import { approveTask, rejectTask } from '../../services/taskService';
import { ApiError } from '../../types/api';

interface ParentTaskDashboardProps {
  tasks: Task[];
  resources: Resource[];
  players: Player[];
  onChanged: () => void;
}

export function ParentTaskDashboard({ tasks, resources, players, onChanged }: ParentTaskDashboardProps) {
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  function playerName(id: number): string {
    return players.find((player) => player.id === id)?.name ?? `#${id}`;
  }

  async function handleApprove(taskId: number): Promise<void> {
    setPendingId(taskId);
    setError(null);
    try {
      await approveTask(taskId);
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Fehler bei der Bestätigung.');
    } finally {
      setPendingId(null);
    }
  }

  async function handleReject(taskId: number): Promise<void> {
    setPendingId(taskId);
    setError(null);
    try {
      await rejectTask(taskId);
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Fehler beim Ablehnen.');
    } finally {
      setPendingId(null);
    }
  }

  const pendingApproval = tasks.filter((task) => task.status === 'completed_pending');
  const otherTasks = tasks.filter((task) => task.status !== 'completed_pending');

  return (
    <div className="parent-dashboard">
      <CreateTaskForm players={players} onCreated={onChanged} />

      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}

      {pendingApproval.length > 0 && (
        <section>
          <h2>Wartet auf Bestätigung</h2>
          <div className="task-list">
            {pendingApproval.map((task) => (
              <TaskCard key={task.id} task={task} resources={resources}>
                <p className="task-assignee">Für: {playerName(task.assignedPlayerId)}</p>
                <div className="task-actions">
                  <button
                    type="button"
                    disabled={pendingId === task.id}
                    onClick={() => {
                      void handleApprove(task.id);
                    }}
                  >
                    Bestätigen
                  </button>
                  <button
                    type="button"
                    disabled={pendingId === task.id}
                    onClick={() => {
                      void handleReject(task.id);
                    }}
                  >
                    Ablehnen
                  </button>
                </div>
              </TaskCard>
            ))}
          </div>
        </section>
      )}

      <section>
        <h2>Alle Aufgaben</h2>
        {otherTasks.length === 0 ? (
          <p>Noch keine weiteren Aufgaben.</p>
        ) : (
          <div className="task-list">
            {otherTasks.map((task) => (
              <TaskCard key={task.id} task={task} resources={resources}>
                <p className="task-assignee">Für: {playerName(task.assignedPlayerId)}</p>
              </TaskCard>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
