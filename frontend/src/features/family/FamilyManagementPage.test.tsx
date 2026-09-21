import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { FamilyManagementPage } from './FamilyManagementPage';
import { AuthProvider } from '../auth/AuthContext';
import * as authService from '../../services/authService';
import * as playerService from '../../services/playerService';
import * as resetService from '../../services/resetService';

vi.mock('../../services/authService', () => ({
  fetchSession: vi.fn(),
  fetchPlayers: vi.fn(),
  logout: vi.fn(),
}));

vi.mock('../../services/playerService', async () => {
  const actual = await vi.importActual<typeof import('../../services/playerService')>('../../services/playerService');
  return {
    ...actual,
    fetchAllPlayers: vi.fn(),
    fetchLoginTokenStatus: vi.fn().mockResolvedValue({ active: false, createdAt: null, lastUsedAt: null }),
  };
});

vi.mock('../../services/resetService', () => ({
  resetFamilyProgress: vi.fn(),
  resetIntro: vi.fn(),
  resetRewards: vi.fn(),
  resetTasks: vi.fn(),
}));

const parentSession = {
  authenticated: true,
  familyId: 1,
  playerId: 1,
  playerRole: 'parent' as const,
  csrfToken: 'test-token',
};

function renderPage(): ReturnType<typeof render> {
  return render(
    <MemoryRouter>
      <AuthProvider>
        <FamilyManagementPage />
      </AuthProvider>
    </MemoryRouter>,
  );
}

beforeEach(() => {
  vi.mocked(resetService.resetFamilyProgress).mockReset();
  vi.mocked(authService.fetchSession).mockResolvedValue(parentSession);
  vi.mocked(authService.fetchPlayers).mockResolvedValue([
    { id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel', hasPhoto: false, introSeenAt: null },
  ]);
  vi.mocked(playerService.fetchAllPlayers).mockResolvedValue([
    { id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel', hasPhoto: false, introSeenAt: null, isActive: true },
    { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false, introSeenAt: null, isActive: true },
  ]);
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('FamilyManagementPage', () => {
  it('setzt den Familien-Fortschritt nach Bestaetigung zurueck', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    vi.mocked(resetService.resetFamilyProgress).mockResolvedValue(undefined);

    renderPage();

    const button = await screen.findByRole('button', { name: 'Gesamten Fortschritt zurücksetzen' });
    fireEvent.click(button);

    expect(window.confirm).toHaveBeenCalled();
    await waitFor(() => expect(resetService.resetFamilyProgress).toHaveBeenCalled());
    expect(await screen.findByText('Fortschritt wurde zurückgesetzt.')).toBeInTheDocument();
  });

  it('tut nichts, wenn der Bestaetigungsdialog abgebrochen wird', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    renderPage();

    const button = await screen.findByRole('button', { name: 'Gesamten Fortschritt zurücksetzen' });
    fireEvent.click(button);

    expect(resetService.resetFamilyProgress).not.toHaveBeenCalled();
  });
});
