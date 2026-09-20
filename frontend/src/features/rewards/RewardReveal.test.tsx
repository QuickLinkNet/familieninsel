import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { RewardReveal } from './RewardReveal';
import type { RewardEvent } from '../../types/reward';
import type { Resource } from '../../types/resource';

const resources: Resource[] = [
  { id: 1, key: 'wood', name: 'Holz', iconKey: 'wood', amount: 0 },
  { id: 2, key: 'metal', name: 'Metall', iconKey: 'metal', amount: 0 },
];

describe('RewardReveal', () => {
  it('nennt bei einer Aufgabe deren Titel und zeigt danach die Rohstoffe', async () => {
    const events: RewardEvent[] = [
      { taskId: 1, taskTitle: 'Zimmer aufraeumen', rewards: [{ resourceKey: 'wood', amount: 3 }], building: null },
    ];
    const onFinished = vi.fn();

    render(<RewardReveal events={events} resources={resources} onFinished={onFinished} />);

    expect(screen.getByText('Deine Aufgabe hat unserer Insel geholfen!')).toBeInTheDocument();
    expect(screen.getByText('„Zimmer aufraeumen“ ist geschafft!')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() => expect(screen.getByText('+3 Holz')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Weiter zur Insel' }));
    expect(onFinished).toHaveBeenCalledTimes(1);
  });

  it('fasst mehrere Aufgaben kompakt zusammen', () => {
    const events: RewardEvent[] = [
      { taskId: 1, taskTitle: 'Tisch decken', rewards: [{ resourceKey: 'wood', amount: 1 }], building: null },
      { taskId: 2, taskTitle: 'Muell rausbringen', rewards: [{ resourceKey: 'metal', amount: 2 }], building: null },
    ];

    render(<RewardReveal events={events} resources={resources} onFinished={vi.fn()} />);

    expect(screen.getByText('„Tisch decken“, „Muell rausbringen“ sind geschafft!')).toBeInTheDocument();
  });

  it('summiert Rohstoffe ueber mehrere Aufgaben hinweg', async () => {
    const events: RewardEvent[] = [
      { taskId: 1, taskTitle: 'Erste Aufgabe', rewards: [{ resourceKey: 'wood', amount: 2 }], building: null },
      { taskId: 2, taskTitle: 'Zweite Aufgabe', rewards: [{ resourceKey: 'wood', amount: 3 }], building: null },
    ];

    render(<RewardReveal events={events} resources={resources} onFinished={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() => expect(screen.getByText('+5 Holz')).toBeInTheDocument());
  });

  it('zeigt einen dritten Schritt mit Baufortschritt, wenn ein Gebaeude betroffen ist', async () => {
    const events: RewardEvent[] = [
      {
        taskId: 1,
        taskTitle: 'Zimmer aufraeumen',
        rewards: [{ resourceKey: 'wood', amount: 10 }],
        building: {
          key: 'beach_hut',
          name: 'Strandhuette',
          beforePercent: 40,
          afterPercent: 58,
          beforeStage: 2,
          afterStage: 3,
          justCompleted: false,
          unlockedMinigameName: null,
          unlockedBuildingName: null,
        },
      },
    ];
    const onFinished = vi.fn();

    render(<RewardReveal events={events} resources={resources} onFinished={onFinished} />);

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() => expect(screen.getByText('+10 Holz')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() => expect(screen.getByText('Strandhuette macht Fortschritte!')).toBeInTheDocument());
    expect(screen.getByText('58% fertig')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Weiter zur Insel' }));
    expect(onFinished).toHaveBeenCalledTimes(1);
  });

  it('zeigt einen groesseren Feiermoment inkl. Freischaltung bei Fertigstellung', async () => {
    const events: RewardEvent[] = [
      {
        taskId: 1,
        taskTitle: 'Letzter Handgriff',
        rewards: [{ resourceKey: 'wood', amount: 5 }],
        building: {
          key: 'beach_hut',
          name: 'Strandhuette',
          beforePercent: 90,
          afterPercent: 100,
          beforeStage: 4,
          afterStage: 5,
          justCompleted: true,
          unlockedMinigameName: 'Schatzsuche am Strand',
          unlockedBuildingName: 'Wachturm',
        },
      },
    ];

    render(<RewardReveal events={events} resources={resources} onFinished={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() => expect(screen.getByText('+5 Holz')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));

    await waitFor(() => expect(screen.getByText('Strandhuette ist fertig! 🎉')).toBeInTheDocument());
    expect(screen.getByText('Neu freigeschaltet: Schatzsuche am Strand!')).toBeInTheDocument();
    expect(screen.getByText('Neues Bauprojekt: Wachturm!')).toBeInTheDocument();
  });
});
