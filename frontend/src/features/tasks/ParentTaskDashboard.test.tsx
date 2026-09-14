import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ParentTaskDashboard } from './ParentTaskDashboard';
import * as taskService from '../../services/taskService';
import type { Task } from '../../types/task';
import type { Player } from '../../types/auth';
import type { Resource } from '../../types/resource';

vi.mock('../../services/taskService');

const players: Player[] = [
  { id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel', hasPhoto: false },
  { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false },
];

const resources: Resource[] = [{ id: 1, key: 'wood', name: 'Holz', iconKey: 'wood', amount: 0 }];

const pendingTask: Task = {
  id: 20,
  title: 'Muell rausbringen',
  description: null,
  status: 'completed_pending',
  assignedPlayerId: 3,
  createdByPlayerId: 1,
  dueDate: null,
  parentNote: null,
  completedAt: '2026-01-01T00:00:00Z',
  approvedAt: null,
  createdAt: '2026-01-01T00:00:00Z',
  rewards: [{ resourceId: 1, amount: 2 }],
};

describe('ParentTaskDashboard', () => {
  it('zeigt Aufgaben, die auf Bestaetigung warten, mit Empfaenger', () => {
    render(
      <ParentTaskDashboard tasks={[pendingTask]} resources={resources} players={players} onChanged={vi.fn()} />,
    );

    expect(screen.getByRole('heading', { name: 'Wartet auf Bestätigung' })).toBeInTheDocument();
    expect(screen.getByText('Für: Emil')).toBeInTheDocument();
  });

  it('ruft approveTask beim Klick auf Bestaetigen auf', async () => {
    const approveTask = vi.mocked(taskService.approveTask).mockResolvedValue();
    const onChanged = vi.fn();

    render(
      <ParentTaskDashboard tasks={[pendingTask]} resources={resources} players={players} onChanged={onChanged} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Bestätigen' }));

    expect(approveTask).toHaveBeenCalledWith(20);
    await vi.waitFor(() => expect(onChanged).toHaveBeenCalled());
  });

  it('ruft rejectTask beim Klick auf Ablehnen auf', async () => {
    const rejectTask = vi.mocked(taskService.rejectTask).mockResolvedValue();
    const onChanged = vi.fn();

    render(
      <ParentTaskDashboard tasks={[pendingTask]} resources={resources} players={players} onChanged={onChanged} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Ablehnen' }));

    expect(rejectTask).toHaveBeenCalledWith(20);
    await vi.waitFor(() => expect(onChanged).toHaveBeenCalled());
  });
});
