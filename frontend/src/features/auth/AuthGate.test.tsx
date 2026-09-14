import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { AuthGate } from './AuthGate';
import * as AuthContextModule from './AuthContext';
import type { SessionState } from '../../types/auth';

function mockAuth(session: SessionState | null, loading = false) {
  vi.spyOn(AuthContextModule, 'useAuth').mockReturnValue({
    session,
    players: [],
    parentCandidates: [{ id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel' }],
    loading,
    error: null,
    loadParentCandidates: vi.fn(),
    verifyParentPin: vi.fn(),
    completeLogin: vi.fn(),
    loginWithQrToken: vi.fn(),
    logout: vi.fn(),
    refreshPlayers: vi.fn(),
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

    expect(screen.getByAltText('Manuel - Elternteil')).toBeInTheDocument();
    expect(screen.queryByText('Geschuetzter Inhalt')).not.toBeInTheDocument();
  });

  it('zeigt den geschuetzten Inhalt fuer ein Kinderprofil', () => {
    mockAuth({
      authenticated: true,
      familyId: 1,
      playerId: 3,
      playerRole: 'child',
      csrfToken: 't',
    });

    render(
      <AuthGate>
        <p>Geschuetzter Inhalt</p>
      </AuthGate>,
    );

    expect(screen.getByText('Geschuetzter Inhalt')).toBeInTheDocument();
  });

  it('zeigt den geschuetzten Inhalt fuer ein Elternprofil', () => {
    mockAuth({
      authenticated: true,
      familyId: 1,
      playerId: 1,
      playerRole: 'parent',
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
