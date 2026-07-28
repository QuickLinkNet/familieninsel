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
      parentUnlocked: false,
      csrfToken: 'test-token',
    }),
  fetchPlayers: () =>
    Promise.resolve([{ id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil' }]),
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
  fetchActiveBuilding: () => Promise.resolve(null),
  contributeToBuilding: vi.fn(),
}));

vi.mock('../services/activityService', () => ({
  fetchActivity: () => Promise.resolve([]),
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
