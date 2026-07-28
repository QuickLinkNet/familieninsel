import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { HomePage } from './HomePage';

vi.mock('../services/healthService', () => ({
  fetchHealth: () =>
    Promise.resolve({ status: 'ok', database: 'connected', time: '2026-01-01T00:00:00Z' }),
}));

describe('HomePage', () => {
  it('zeigt den Titel Familien-Insel', () => {
    render(<HomePage />);
    expect(screen.getByRole('heading', { name: 'Familien-Insel' })).toBeInTheDocument();
  });
});
