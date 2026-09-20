import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HomePage } from './HomePage';
import { AuthProvider } from '../features/auth/AuthContext';
import * as authService from '../services/authService';

function renderHomePage(): ReturnType<typeof render> {
  return render(
    <MemoryRouter>
      <AuthProvider>
        <HomePage />
      </AuthProvider>
    </MemoryRouter>,
  );
}

vi.mock('../services/authService', () => ({
  fetchSession: vi.fn(),
  fetchPlayers: vi.fn(),
  logout: vi.fn(),
}));

vi.mock('../services/playerService', () => ({
  markIntroSeen: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../services/taskService', () => ({
  fetchTasks: () => Promise.resolve([]),
  completeTask: vi.fn(),
}));

vi.mock('../services/resourceService', () => ({
  fetchResources: () => Promise.resolve([]),
}));

vi.mock('../services/buildingService', () => ({
  fetchBuildings: () => Promise.resolve([]),
  contributeToBuilding: vi.fn(),
}));

vi.mock('../services/activityService', () => ({
  fetchActivity: () => Promise.resolve([]),
}));

vi.mock('../services/minigameService', () => ({
  fetchMinigames: () => Promise.resolve([]),
  completeMinigame: vi.fn(),
}));

const childSession = {
  authenticated: true,
  familyId: 1,
  playerId: 3,
  playerRole: 'child' as const,
  csrfToken: 'test-token',
};

const parentSession = {
  authenticated: true,
  familyId: 1,
  playerId: 1,
  playerRole: 'parent' as const,
  csrfToken: 'test-token',
};

beforeEach(() => {
  vi.mocked(authService.fetchSession).mockResolvedValue(childSession);
});

describe('HomePage', () => {
  it('zeigt den Titel und den angemeldeten Spieler', async () => {
    vi.mocked(authService.fetchPlayers).mockResolvedValue([
      { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false, introSeenAt: '2026-01-01T00:00:00.000Z' },
    ]);

    renderHomePage();

    expect(screen.getByRole('heading', { name: 'Familien-Insel' })).toBeInTheDocument();
    expect(await screen.findByText('Emil')).toBeInTheDocument();
  });

  it('zeigt das Story-Intro fuer ein Kind, das es noch nicht gesehen hat', async () => {
    vi.mocked(authService.fetchPlayers).mockResolvedValue([
      { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false, introSeenAt: null },
    ]);

    renderHomePage();

    expect(await screen.findByText('Ein wilder Sturm hat euer Boot erwischt!')).toBeInTheDocument();
  });

  it('zeigt kein Intro fuer ein Kind, das es schon gesehen hat', async () => {
    vi.mocked(authService.fetchPlayers).mockResolvedValue([
      { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false, introSeenAt: '2026-01-01T00:00:00.000Z' },
    ]);

    renderHomePage();

    await screen.findByText('Emil');
    expect(screen.queryByText('Ein wilder Sturm hat euer Boot erwischt!')).not.toBeInTheDocument();
  });

  it('zeigt nie ein Intro fuer Eltern, auch ohne introSeenAt', async () => {
    vi.mocked(authService.fetchSession).mockResolvedValue(parentSession);
    vi.mocked(authService.fetchPlayers).mockResolvedValue([
      { id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel', hasPhoto: false, introSeenAt: null },
    ]);

    renderHomePage();

    await screen.findByText('Manuel');
    expect(screen.queryByText('Ein wilder Sturm hat euer Boot erwischt!')).not.toBeInTheDocument();
  });
});
