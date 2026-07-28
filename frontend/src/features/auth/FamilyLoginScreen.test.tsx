import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FamilyLoginScreen } from './FamilyLoginScreen';
import * as AuthContextModule from './AuthContext';

function mockAuth(overrides: Partial<ReturnType<typeof AuthContextModule.useAuth>> = {}) {
  vi.spyOn(AuthContextModule, 'useAuth').mockReturnValue({
    session: null,
    players: [],
    loading: false,
    error: null,
    loginWithFamilyCode: vi.fn().mockResolvedValue(true),
    chooseProfile: vi.fn(),
    unlockParent: vi.fn(),
    logout: vi.fn(),
    ...overrides,
  });
}

describe('FamilyLoginScreen', () => {
  it('deaktiviert den Button bei leerem Code', () => {
    mockAuth();
    render(<FamilyLoginScreen />);

    expect(screen.getByRole('button', { name: /los geht/i })).toBeDisabled();
  });

  it('ruft loginWithFamilyCode beim Absenden auf', () => {
    const loginWithFamilyCode = vi.fn().mockResolvedValue(true);
    mockAuth({ loginWithFamilyCode });

    render(<FamilyLoginScreen />);
    fireEvent.change(screen.getByLabelText('Familiencode'), { target: { value: 'INSEL2026' } });
    fireEvent.click(screen.getByRole('button', { name: /los geht/i }));

    expect(loginWithFamilyCode).toHaveBeenCalledWith('INSEL2026');
  });

  it('zeigt eine Fehlermeldung an', () => {
    mockAuth({ error: 'Der Familiencode ist nicht korrekt.' });
    render(<FamilyLoginScreen />);

    expect(screen.getByRole('alert')).toHaveTextContent('Der Familiencode ist nicht korrekt.');
  });
});
