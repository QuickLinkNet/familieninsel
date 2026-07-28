import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ChildTaskList } from './ChildTaskList';
import * as taskService from '../../services/taskService';
import type { Task } from '../../types/task';
import type { Resource } from '../../types/resource';

vi.mock('../../services/taskService');

const resources: Resource[] = [{ id: 1, key: 'wood', name: 'Holz', iconKey: 'wood', amount: 0 }];

const openTask: Task = {
  id: 10,
  title: 'Zimmer aufräumen',
  description: null,
  status: 'open',
  assignedPlayerId: 3,
  createdByPlayerId: 2,
  dueDate: null,
  parentNote: null,
  completedAt: null,
  approvedAt: null,
  createdAt: '2026-01-01T00:00:00Z',
  rewards: [{ resourceId: 1, amount: 5 }],
};

describe('ChildTaskList', () => {
  it('zeigt einen Hinweis, wenn keine Aufgaben vorhanden sind', () => {
    render(<ChildTaskList tasks={[]} resources={resources} onChanged={vi.fn()} />);
    expect(screen.getByText(/keine Aufgaben/i)).toBeInTheDocument();
  });

  it('zeigt den Erledigt-Button nur fuer offene Aufgaben und ruft completeTask auf', async () => {
    const completeTask = vi.mocked(taskService.completeTask).mockResolvedValue();
    const onChanged = vi.fn();

    render(<ChildTaskList tasks={[openTask]} resources={resources} onChanged={onChanged} />);

    const button = screen.getByRole('button', { name: 'Erledigt!' });
    fireEvent.click(button);

    expect(completeTask).toHaveBeenCalledWith(10);
    await vi.waitFor(() => expect(onChanged).toHaveBeenCalled());
  });

  it('zeigt keinen Erledigt-Button fuer bereits gemeldete Aufgaben', () => {
    const pendingTask: Task = { ...openTask, status: 'completed_pending' };
    render(<ChildTaskList tasks={[pendingTask]} resources={resources} onChanged={vi.fn()} />);

    expect(screen.queryByRole('button', { name: 'Erledigt!' })).not.toBeInTheDocument();
    expect(screen.getByText('Wartet auf Bestätigung')).toBeInTheDocument();
  });
});
