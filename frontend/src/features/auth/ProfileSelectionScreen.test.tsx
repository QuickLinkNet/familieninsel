import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ProfileSelectionScreen } from './ProfileSelectionScreen';
import * as AuthContextModule from './AuthContext';
import type { Player } from '../../types/auth';

const players: Player[] = [
  { id: 1, name: 'Manuel', age: null, role: 'parent', avatarKey: 'manuel' },
  { id: 3, name: 'Emil', age: 5, role: 'child', avatarKey: 'emil' },
];

function mockAuth(overrides: Partial<ReturnType<typeof AuthContextModule.useAuth>> = {}) {
  vi.spyOn(AuthContextModule, 'useAuth').mockReturnValue({
    session: null,
    players,
    loading: false,
    error: null,
    loginWithFamilyCode: vi.fn(),
    chooseProfile: vi.fn().mockResolvedValue(true),
    unlockParent: vi.fn(),
    logout: vi.fn(),
    ...overrides,
  });
}

describe('ProfileSelectionScreen', () => {
  it('zeigt alle Profile an', () => {
    mockAuth();
    render(<ProfileSelectionScreen />);

    expect(screen.getByText('Manuel')).toBeInTheDocument();
    expect(screen.getByText('Emil')).toBeInTheDocument();
    expect(screen.getByText('Elternteil')).toBeInTheDocument();
  });

  it('ruft chooseProfile mit der richtigen ID auf', () => {
    const chooseProfile = vi.fn().mockResolvedValue(true);
    mockAuth({ chooseProfile });

    render(<ProfileSelectionScreen />);
    fireEvent.click(screen.getByText('Emil'));

    expect(chooseProfile).toHaveBeenCalledWith(3);
  });
});
