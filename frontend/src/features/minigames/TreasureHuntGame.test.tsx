import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { TreasureHuntGame } from './TreasureHuntGame';
import * as minigameService from '../../services/minigameService';

vi.mock('../../services/minigameService');

describe('TreasureHuntGame', () => {
  it('zeigt 5 versteckte Objekte und 0/5 gefunden', () => {
    render(<TreasureHuntGame onCompleted={vi.fn()} />);

    expect(screen.getByText('0/5 gefunden')).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Verstecktes Objekt' })).toHaveLength(5);
  });

  it('deckt ein Objekt beim Klick auf', () => {
    render(<TreasureHuntGame onCompleted={vi.fn()} />);

    const spots = screen.getAllByRole('button', { name: 'Verstecktes Objekt' });
    fireEvent.click(spots[0]);

    expect(screen.getByText('1/5 gefunden')).toBeInTheDocument();
  });

  it('ruft completeMinigame auf, wenn alle 5 gefunden wurden, und meldet die Sterne', async () => {
    const complete = vi.mocked(minigameService.completeMinigame).mockResolvedValue({ starsAwarded: 2 });
    const onCompleted = vi.fn();

    render(<TreasureHuntGame onCompleted={onCompleted} />);

    for (const spot of screen.getAllByRole('button', { name: 'Verstecktes Objekt' })) {
      fireEvent.click(spot);
    }

    await vi.waitFor(() => expect(complete).toHaveBeenCalledWith('schatzsuche'));
    await vi.waitFor(() => expect(onCompleted).toHaveBeenCalledWith(2));
  });
});
