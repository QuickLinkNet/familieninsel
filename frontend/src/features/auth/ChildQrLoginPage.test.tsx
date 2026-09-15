import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { ChildQrLoginPage } from './ChildQrLoginPage';
import * as AuthContextModule from './AuthContext';

function mockAuth(overrides: Partial<ReturnType<typeof AuthContextModule.useAuth>> = {}) {
  vi.spyOn(AuthContextModule, 'useAuth').mockReturnValue({
    session: null,
    players: [],
    parentCandidates: [],
    loading: false,
    error: null,
    loadParentCandidates: vi.fn(),
    verifyParentPin: vi.fn(),
    completeLogin: vi.fn(),
    loginWithQrToken: vi.fn().mockResolvedValue(true),
    logout: vi.fn(),
    refreshPlayers: vi.fn(),
    ...overrides,
  });
}

function renderAt(token: string) {
  return render(
    <MemoryRouter initialEntries={[`/kind/${token}`]}>
      <Routes>
        <Route path="/kind/:token" element={<ChildQrLoginPage />} />
        <Route path="/" element={<div>Startseite</div>} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('ChildQrLoginPage', () => {
  it('loest den Login noch nicht aus, solange die Session (und damit das CSRF-Token) laedt', () => {
    // AuthProvider.refresh() holt per /auth/session erst das CSRF-Token,
    // bevor der QR-Login-POST ueberhaupt ein gueltiges Token mitschicken
    // kann - vorher darf hier nicht schon losgeschickt werden.
    const loginWithQrToken = vi.fn().mockResolvedValue(true);
    mockAuth({ loginWithQrToken, loading: true });

    renderAt('abc123');

    expect(loginWithQrToken).not.toHaveBeenCalled();
  });

  it('meldet sich an, sobald das Laden abgeschlossen ist', async () => {
    const loginWithQrToken = vi.fn().mockResolvedValue(true);
    mockAuth({ loginWithQrToken, loading: false });

    renderAt('abc123');

    await waitFor(() => expect(loginWithQrToken).toHaveBeenCalledWith('abc123'));
    await waitFor(() => expect(screen.getByText('Startseite')).toBeInTheDocument());
  });

  it('zeigt eine Fehlermeldung, wenn der Login fehlschlaegt', async () => {
    const loginWithQrToken = vi.fn().mockResolvedValue(false);
    mockAuth({ loginWithQrToken, loading: false, error: 'Ungueltiges oder fehlendes CSRF-Token.' });

    renderAt('abc123');

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('Ungueltiges oder fehlendes CSRF-Token.'),
    );
  });
});
