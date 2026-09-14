import { useState } from 'react';
import type { FormEvent } from 'react';
import * as playerService from '../../services/playerService';

interface AddChildFormProps {
  onAdded: () => void;
}

export function AddChildForm({ onAdded }: AddChildFormProps) {
  const [name, setName] = useState('');
  const [age, setAge] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      await playerService.createChild(name.trim(), age === '' ? null : Number(age));
      setName('');
      setAge('');
      onAdded();
    } catch {
      setError('Kind konnte nicht angelegt werden.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="add-child-form" onSubmit={handleSubmit}>
      <input
        type="text"
        value={name}
        onChange={(event) => setName(event.target.value)}
        placeholder="Name"
        aria-label="Name des Kindes"
        disabled={submitting}
      />
      <input
        type="number"
        min={0}
        max={17}
        value={age}
        onChange={(event) => setAge(event.target.value)}
        placeholder="Alter (optional)"
        aria-label="Alter des Kindes"
        disabled={submitting}
      />
      <button type="submit" disabled={submitting || name.trim() === ''}>
        Kind hinzufügen
      </button>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </form>
  );
}
