import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { HomePage } from './HomePage';
import { AuthProvider } from '../features/auth/AuthContext';

vi.mock('../services/authService', () => ({
  fetchSession: () =>
    Promise.resolve({
      authenticated: true,
      familyId: 1,
      playerId: 3,
      playerRole: 'child',
      csrfToken: 'test-token',
    }),
  fetchPlayers: () =>
    Promise.resolve([{ id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil', hasPhoto: false }]),
  logout: vi.fn(),
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

describe('HomePage', () => {
  it('zeigt den Titel und den angemeldeten Spieler', async () => {
    render(
      <AuthProvider>
        <HomePage />
      </AuthProvider>,
    );

    expect(screen.getByRole('heading', { name: 'Familien-Insel' })).toBeInTheDocument();
    expect(await screen.findByText('Emil')).toBeInTheDocument();
  });
});
