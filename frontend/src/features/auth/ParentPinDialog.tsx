import { useState } from 'react';
import type { FormEvent } from 'react';
import { useAuth } from './AuthContext';

export function ParentPinDialog() {
  const { unlockParent, error, loading } = useAuth();
  const [pin, setPin] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setSubmitting(true);
    const success = await unlockParent(pin);
    setSubmitting(false);
    if (!success) {
      setPin('');
    }
  }

  return (
    <main className="auth-screen">
      <h1>Eltern-PIN</h1>
      <p>Bitte gib deine 4-stellige PIN ein.</p>
      <form className="auth-form" onSubmit={handleSubmit}>
        <input
          type="password"
          inputMode="numeric"
          pattern="[0-9]*"
          maxLength={4}
          autoComplete="off"
          value={pin}
          onChange={(event) => setPin(event.target.value.replace(/\D/g, ''))}
          aria-label="Eltern-PIN"
          disabled={loading || submitting}
        />
        <button type="submit" disabled={loading || submitting || pin.length !== 4}>
          Bestätigen
        </button>
      </form>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </main>
  );
}
