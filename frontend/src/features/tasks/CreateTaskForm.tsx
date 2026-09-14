import { useState } from 'react';
import type { FormEvent } from 'react';
import type { Player } from '../../types/auth';
import { createTask } from '../../services/taskService';
import { ApiError } from '../../types/api';
import { ResourceIcon } from '../resources/ResourceIcon';

interface CreateTaskFormProps {
  players: Player[];
  onCreated: () => void;
}

const REWARD_RESOURCE_KEYS: Array<{ key: string; label: string }> = [
  { key: 'wood', label: 'Holz' },
  { key: 'metal', label: 'Metall' },
  { key: 'fabric', label: 'Stoff' },
  { key: 'rope', label: 'Seil' },
  { key: 'stars', label: 'Sterne' },
];

export function CreateTaskForm({ players, onCreated }: CreateTaskFormProps) {
  const [title, setTitle] = useState('');
  const [assignedPlayerId, setAssignedPlayerId] = useState<number | ''>('');
  const [rewards, setRewards] = useState<Record<string, number>>({});
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function updateReward(key: string, value: string): void {
    const amount = Math.max(0, Math.min(99, Number(value) || 0));
    setRewards((previous) => ({ ...previous, [key]: amount }));
  }

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    if (assignedPlayerId === '') {
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      await createTask({ assignedPlayerId, title, rewards });
      setTitle('');
      setRewards({});
      setAssignedPlayerId('');
      onCreated();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Fehler beim Erstellen der Aufgabe.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="create-task-form" onSubmit={handleSubmit}>
      <h2>Aufgabe erstellen</h2>
      <label>
        Titel
        <input
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          maxLength={120}
          required
        />
      </label>
      <label>
        Für
        <select
          value={assignedPlayerId}
          onChange={(event) => setAssignedPlayerId(event.target.value === '' ? '' : Number(event.target.value))}
          required
        >
          <option value="">Bitte wählen</option>
          {players.map((player) => (
            <option key={player.id} value={player.id}>
              {player.name}
            </option>
          ))}
        </select>
      </label>
      <fieldset>
        <legend>Belohnung</legend>
        {REWARD_RESOURCE_KEYS.map(({ key, label }) => (
          <label key={key} className="reward-input">
            <span className="reward-input__label">
              <ResourceIcon resourceKey={key} className="reward-input__icon" />
              {label}
            </span>
            <input
              type="number"
              min={0}
              max={99}
              value={rewards[key] ?? 0}
              onChange={(event) => updateReward(key, event.target.value)}
            />
          </label>
        ))}
      </fieldset>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
      <button type="submit" disabled={submitting || title.trim() === '' || assignedPlayerId === ''}>
        Aufgabe erstellen
      </button>
    </form>
  );
}
