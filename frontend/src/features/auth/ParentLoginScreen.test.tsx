import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ParentLoginScreen } from './ParentLoginScreen';
import * as AuthContextModule from './AuthContext';
import type { ParentCandidate } from '../../types/auth';

const parentCandidates: ParentCandidate[] = [
  { id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel' },
  { id: 2, name: 'Kathrin', age: null, role: 'parent', avatarKey: 'kathrin' },
];

function mockAuth(overrides: Partial<ReturnType<typeof AuthContextModule.useAuth>> = {}) {
  vi.spyOn(AuthContextModule, 'useAuth').mockReturnValue({
    session: null,
    players: [],
    parentCandidates,
    loading: false,
    error: null,
    loadParentCandidates: vi.fn(),
    verifyParentPin: vi.fn().mockResolvedValue(true),
    completeLogin: vi.fn(),
    loginWithQrToken: vi.fn(),
    logout: vi.fn(),
    refreshPlayers: vi.fn(),
    ...overrides,
  });
}

function enterPin(pin: string): void {
  for (const digit of pin) {
    fireEvent.click(screen.getByRole('button', { name: digit }));
  }
}

describe('ParentLoginScreen', () => {
  it('zeigt zunaechst die Elternprofile zur Auswahl', () => {
    mockAuth();
    render(<ParentLoginScreen />);

    expect(screen.getByAltText('Manuel - Elternteil')).toBeInTheDocument();
    expect(screen.getByAltText('Kathrin - Elternteil')).toBeInTheDocument();
  });

  it('zeigt nach Profilwahl das PIN-Feld und ruft verifyParentPin bei vollstaendiger PIN auf', async () => {
    const verifyParentPin = vi.fn().mockResolvedValue(true);
    const completeLogin = vi.fn();
    mockAuth({ verifyParentPin, completeLogin });

    render(<ParentLoginScreen />);
    fireEvent.click(screen.getByAltText('Manuel - Elternteil'));

    await waitFor(() => expect(screen.getByText('Bitte gib deinen PIN ein')).toBeInTheDocument());

    enterPin('2026');

    await waitFor(() => expect(verifyParentPin).toHaveBeenCalledWith(1, '2026'));
    await waitFor(() => expect(screen.getByText('Willkommen zurück, Manuel!')).toBeInTheDocument());
    await waitFor(() => expect(completeLogin).toHaveBeenCalled(), { timeout: 2000 });
  });

  it('zeigt bei falscher PIN einen Fehler und erlaubt danach erneute Eingabe', async () => {
    const verifyParentPin = vi.fn().mockResolvedValue(false);
    mockAuth({ verifyParentPin });

    render(<ParentLoginScreen />);
    fireEvent.click(screen.getByAltText('Manuel - Elternteil'));
    await waitFor(() => expect(screen.getByText('Bitte gib deinen PIN ein')).toBeInTheDocument());

    enterPin('0000');

    await waitFor(() => expect(screen.getByText('Hmm… der PIN stimmt nicht.')).toBeInTheDocument());
    await waitFor(() => expect(screen.getByText('Bitte gib deinen PIN ein')).toBeInTheDocument(), { timeout: 2000 });
  });

  it('kehrt ueber den Zurueck-Button zur Profilauswahl zurueck', async () => {
    mockAuth();
    render(<ParentLoginScreen />);

    fireEvent.click(screen.getByAltText('Manuel - Elternteil'));
    await waitFor(() => expect(screen.getByText('Bitte gib deinen PIN ein')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Zurück' }));

    await waitFor(() => expect(screen.getByAltText('Manuel - Elternteil')).toBeInTheDocument());
  });
});
