import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ChildResetControl } from './ChildResetControl';
import * as resetService from '../../services/resetService';

vi.mock('../../services/resetService', () => ({
  resetIntro: vi.fn(),
  resetRewards: vi.fn(),
  resetTasks: vi.fn(),
}));

afterEach(() => {
  vi.restoreAllMocks();
});

describe('ChildResetControl', () => {
  it('fragt vor dem Zuruecksetzen nach und ruft bei Bestaetigung den passenden Service auf', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    vi.mocked(resetService.resetTasks).mockResolvedValue(undefined);

    render(<ChildResetControl playerId={3} childName="Emil" />);
    fireEvent.click(screen.getByRole('button', { name: 'Aufgaben zurücksetzen' }));

    expect(window.confirm).toHaveBeenCalled();
    await waitFor(() => expect(resetService.resetTasks).toHaveBeenCalledWith(3));
    expect(await screen.findByText('Erledigt.')).toBeInTheDocument();
  });

  it('tut nichts, wenn der Bestaetigungsdialog abgebrochen wird', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    render(<ChildResetControl playerId={3} childName="Emil" />);
    fireEvent.click(screen.getByRole('button', { name: 'Intro erneut zeigen' }));

    expect(resetService.resetIntro).not.toHaveBeenCalled();
  });

  it('zeigt eine Fehlermeldung, wenn der Request fehlschlaegt', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    vi.mocked(resetService.resetRewards).mockRejectedValue(new Error('boom'));

    render(<ChildResetControl playerId={3} childName="Emil" />);
    fireEvent.click(screen.getByRole('button', { name: 'Belohnungen erneut zeigen' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Zurücksetzen fehlgeschlagen.');
  });
});
