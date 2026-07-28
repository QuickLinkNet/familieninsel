import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MinigameSection } from './MinigameSection';
import type { Minigame } from '../../types/minigame';

const lockedMinigame: Minigame = {
  id: 1,
  key: 'schatzsuche',
  name: 'Schatzsuche am Strand',
  description: null,
  unlocked: false,
  firstCompletedAt: null,
};

describe('MinigameSection', () => {
  it('zeigt den gesperrten Zustand, wenn das Minispiel nicht freigeschaltet ist', () => {
    render(<MinigameSection minigames={[lockedMinigame]} onChanged={vi.fn()} />);

    expect(screen.getByText(/noch gesperrt/i)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Spielen' })).not.toBeInTheDocument();
  });

  it('zeigt einen Spielen-Button, wenn freigeschaltet, und startet das Spiel per Klick', () => {
    const unlocked: Minigame = { ...lockedMinigame, unlocked: true };
    render(<MinigameSection minigames={[unlocked]} onChanged={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Spielen' }));

    expect(screen.getByText('0/5 gefunden')).toBeInTheDocument();
  });

  it('zeigt "Nochmal spielen", wenn bereits einmal abgeschlossen', () => {
    const completed: Minigame = { ...lockedMinigame, unlocked: true, firstCompletedAt: '2026-01-01T00:00:00Z' };
    render(<MinigameSection minigames={[completed]} onChanged={vi.fn()} />);

    expect(screen.getByRole('button', { name: 'Nochmal spielen' })).toBeInTheDocument();
  });
});
