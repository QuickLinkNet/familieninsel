import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { CreateTaskForm } from './CreateTaskForm';
import * as taskService from '../../services/taskService';
import type { Player } from '../../types/auth';

vi.mock('../../services/taskService');

const players: Player[] = [
  { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false },
  { id: 4, name: 'Thea', age: 7, role: 'child', avatarKey: 'thea', hasPhoto: false },
];

describe('CreateTaskForm', () => {
  it('deaktiviert den Submit-Button ohne Titel und Empfaenger', () => {
    render(<CreateTaskForm players={players} onCreated={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Aufgabe erstellen' })).toBeDisabled();
  });

  it('ruft createTask mit den eingegebenen Werten auf', async () => {
    const createTask = vi.mocked(taskService.createTask).mockResolvedValue(42);
    const onCreated = vi.fn();

    render(<CreateTaskForm players={players} onCreated={onCreated} />);

    fireEvent.change(screen.getByLabelText('Titel'), { target: { value: 'Tisch decken' } });
    fireEvent.change(screen.getByLabelText('Für'), { target: { value: '3' } });
    fireEvent.change(screen.getByLabelText('Holz'), { target: { value: '4' } });

    fireEvent.click(screen.getByRole('button', { name: 'Aufgabe erstellen' }));

    await vi.waitFor(() => expect(createTask).toHaveBeenCalled());
    expect(createTask).toHaveBeenCalledWith({
      assignedPlayerId: 3,
      title: 'Tisch decken',
      rewards: { wood: 4 },
    });
    await vi.waitFor(() => expect(onCreated).toHaveBeenCalled());
  });
});
