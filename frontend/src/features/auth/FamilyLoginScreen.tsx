import { useState } from 'react';
import type { FormEvent } from 'react';
import { useAuth } from './AuthContext';

export function FamilyLoginScreen() {
  const { loginWithFamilyCode, error, loading } = useAuth();
  const [code, setCode] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setSubmitting(true);
    await loginWithFamilyCode(code.trim());
    setSubmitting(false);
  }

  return (
    <main className="auth-screen">
      <h1>Familien-Insel</h1>
      <p>Gib euren Familiencode ein, um loszulegen.</p>
      <form className="auth-form" onSubmit={handleSubmit}>
        <input
          type="text"
          autoComplete="off"
          value={code}
          onChange={(event) => setCode(event.target.value)}
          placeholder="Familiencode"
          aria-label="Familiencode"
          disabled={loading || submitting}
        />
        <button type="submit" disabled={loading || submitting || code.trim() === ''}>
          Los geht&apos;s
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
