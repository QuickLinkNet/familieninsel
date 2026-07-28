import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { AuthGate } from './AuthGate';
import * as AuthContextModule from './AuthContext';
import type { SessionState } from '../../types/auth';

function mockAuth(session: SessionState | null, loading = false) {
  vi.spyOn(AuthContextModule, 'useAuth').mockReturnValue({
    session,
    players: [],
    loading,
    error: null,
    loginWithFamilyCode: vi.fn(),
    chooseProfile: vi.fn(),
    unlockParent: vi.fn(),
    logout: vi.fn(),
  });
}

describe('AuthGate', () => {
  it('zeigt den Login-Screen, wenn nicht authentifiziert', () => {
    mockAuth(null);
    render(
      <AuthGate>
        <p>Geschuetzter Inhalt</p>
      </AuthGate>,
    );

    expect(screen.getByRole('heading', { name: 'Familien-Insel' })).toBeInTheDocument();
    expect(screen.queryByText('Geschuetzter Inhalt')).not.toBeInTheDocument();
  });

  it('zeigt die Profilauswahl, wenn Familie angemeldet aber kein Profil gewaehlt ist', () => {
    mockAuth({
      authenticated: true,
      familyId: 1,
      playerId: null,
      playerRole: null,
      parentUnlocked: false,
      csrfToken: 't',
    });

    render(
      <AuthGate>
        <p>Geschuetzter Inhalt</p>
      </AuthGate>,
    );

    expect(screen.getByRole('heading', { name: 'Wer bist du?' })).toBeInTheDocument();
  });

  it('verlangt die Eltern-PIN, wenn ein Elternprofil gewaehlt aber nicht entsperrt ist', () => {
    mockAuth({
      authenticated: true,
      familyId: 1,
      playerId: 1,
      playerRole: 'parent',
      parentUnlocked: false,
      csrfToken: 't',
    });

    render(
      <AuthGate>
        <p>Geschuetzter Inhalt</p>
      </AuthGate>,
    );

    expect(screen.getByRole('heading', { name: 'Eltern-PIN' })).toBeInTheDocument();
  });

  it('zeigt den geschuetzten Inhalt fuer ein Kinderprofil ohne PIN-Zwang', () => {
    mockAuth({
      authenticated: true,
      familyId: 1,
      playerId: 3,
      playerRole: 'child',
      parentUnlocked: false,
      csrfToken: 't',
    });

    render(
      <AuthGate>
        <p>Geschuetzter Inhalt</p>
      </AuthGate>,
    );

    expect(screen.getByText('Geschuetzter Inhalt')).toBeInTheDocument();
  });

  it('zeigt den geschuetzten Inhalt fuer ein entsperrtes Elternprofil', () => {
    mockAuth({
      authenticated: true,
      familyId: 1,
      playerId: 1,
      playerRole: 'parent',
      parentUnlocked: true,
      csrfToken: 't',
    });

    render(
      <AuthGate>
        <p>Geschuetzter Inhalt</p>
      </AuthGate>,
    );

    expect(screen.getByText('Geschuetzter Inhalt')).toBeInTheDocument();
  });
});
