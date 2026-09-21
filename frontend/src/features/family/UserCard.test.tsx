import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { UserCard } from './UserCard';
import type { ManagedPlayer } from '../../types/auth';
import * as playerService from '../../services/playerService';

vi.spyOn(playerService, 'fetchLoginTokenStatus').mockResolvedValue({
  active: false,
  createdAt: null,
  lastUsedAt: null,
});

const parent: ManagedPlayer = {
  id: 1,
  name: 'Manuel',
  age: null,
  role: 'parent',
  avatarKey: 'manuel',
  hasPhoto: false,
  introSeenAt: null,
  isActive: true,
};

const child: ManagedPlayer = {
  id: 3,
  name: 'Emil',
  age: 5,
  role: 'child',
  avatarKey: 'emil',
  hasPhoto: false,
  introSeenAt: null,
  isActive: true,
};

describe('UserCard', () => {
  it('zeigt PIN-Verwaltung fuer Elternprofile', () => {
    render(<UserCard player={parent} isSelf={false} onChanged={() => {}} />);

    expect(screen.getByRole('button', { name: 'Neue PIN setzen' })).toBeInTheDocument();
  });

  it('deaktiviert den Deaktivieren-Button fuer das eigene Profil', () => {
    render(<UserCard player={parent} isSelf onChanged={() => {}} />);

    expect(screen.getByRole('button', { name: 'Deaktivieren' })).toBeDisabled();
    expect(screen.getByText('Du kannst dich nicht selbst deaktivieren.')).toBeInTheDocument();
  });

  it('zeigt QR-Verwaltung fuer aktive Kinderprofile', async () => {
    render(<UserCard player={child} isSelf={false} onChanged={() => {}} />);

    expect(await screen.findByText('Noch kein QR-Code erstellt')).toBeInTheDocument();
  });

  it('zeigt einen Hinweis statt QR-Verwaltung fuer deaktivierte Kinder', () => {
    render(<UserCard player={{ ...child, isActive: false }} isSelf={false} onChanged={() => {}} />);

    expect(screen.getByText('Deaktivierte Kinder können sich nicht mehr per QR-Code anmelden.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reaktivieren' })).toBeInTheDocument();
  });

  it('zeigt Reset-Optionen fuer Kinder, aber nicht fuer Eltern', async () => {
    render(<UserCard player={child} isSelf={false} onChanged={() => {}} />);
    expect(await screen.findByRole('button', { name: 'Aufgaben zurücksetzen' })).toBeInTheDocument();

    render(<UserCard player={parent} isSelf={false} onChanged={() => {}} />);
    expect(screen.queryAllByRole('button', { name: 'Aufgaben zurücksetzen' })).toHaveLength(1);
  });
});
