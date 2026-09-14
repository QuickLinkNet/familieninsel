import { useState } from 'react';
import type { FormEvent } from 'react';
import * as playerService from '../../services/playerService';

interface EditNameAgeFormProps {
  playerId: number;
  initialName: string;
  initialAge: number | null;
  onSaved: () => void;
  onCancel: () => void;
}

export function EditNameAgeForm({ playerId, initialName, initialAge, onSaved, onCancel }: EditNameAgeFormProps) {
  const [name, setName] = useState(initialName);
  const [age, setAge] = useState(initialAge !== null ? String(initialAge) : '');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      await playerService.updatePlayer(playerId, name.trim(), age === '' ? null : Number(age));
      onSaved();
    } catch {
      setError('Änderung konnte nicht gespeichert werden.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="edit-name-age-form" onSubmit={handleSubmit}>
      <input
        type="text"
        value={name}
        onChange={(event) => setName(event.target.value)}
        placeholder="Name"
        aria-label="Name"
        disabled={submitting}
      />
      <input
        type="number"
        min={0}
        max={120}
        value={age}
        onChange={(event) => setAge(event.target.value)}
        placeholder="Alter"
        aria-label="Alter"
        disabled={submitting}
      />
      <div className="edit-name-age-form__actions">
        <button type="submit" disabled={submitting || name.trim() === ''}>
          Speichern
        </button>
        <button type="button" className="auth-form__secondary" onClick={onCancel} disabled={submitting}>
          Abbrechen
        </button>
      </div>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </form>
  );
}
