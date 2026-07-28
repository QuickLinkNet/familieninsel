import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { BuildingProgress } from './BuildingProgress';
import * as buildingService from '../../services/buildingService';
import type { Building } from '../../types/building';
import type { Resource } from '../../types/resource';

vi.mock('../../services/buildingService');

const resources: Resource[] = [
  { id: 1, key: 'wood', name: 'Holz', iconKey: 'wood', amount: 20 },
  { id: 2, key: 'metal', name: 'Metall', iconKey: 'metal', amount: 3 },
];

const building: Building = {
  id: 1,
  familyBuildingId: 1,
  key: 'beach_hut',
  name: 'Strandhütte',
  description: null,
  status: 'in_progress',
  stage: 2,
  progressPercent: 25,
  completedAt: null,
  unlockMinigameKey: 'schatzsuche',
  costs: [
    { resourceId: 1, required: 20, contributed: 5 },
    { resourceId: 2, required: 10, contributed: 0 },
  ],
};

describe('BuildingProgress', () => {
  it('zeigt Name, Baustufe und Fortschritt', () => {
    render(
      <BuildingProgress building={building} resources={resources} isParent={false} onChanged={vi.fn()} />,
    );

    expect(screen.getByRole('heading', { name: 'Strandhütte' })).toBeInTheDocument();
    expect(screen.getByText('Baustufe: Fundament')).toBeInTheDocument();
    expect(screen.getByText('25% fertig')).toBeInTheDocument();
    expect(screen.getByText('Holz: 5/20')).toBeInTheDocument();
  });

  it('zeigt keine Einzahl-Eingaben fuer Kinder', () => {
    render(
      <BuildingProgress building={building} resources={resources} isParent={false} onChanged={vi.fn()} />,
    );

    expect(screen.queryByLabelText(/einzahlen/i)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /alle verfügbaren/i })).not.toBeInTheDocument();
  });

  it('befuellt Eingaben mit verfuegbaren Mengen ueber den Schnellauswahl-Button', () => {
    render(
      <BuildingProgress building={building} resources={resources} isParent={true} onChanged={vi.fn()} />,
    );

    fireEvent.click(screen.getByRole('button', { name: /alle verfügbaren/i }));

    // Holz: min(20 verfuegbar, 15 noch benoetigt) = 15; Metall: min(3, 10) = 3
    expect(screen.getByLabelText('Holz einzahlen')).toHaveValue(15);
    expect(screen.getByLabelText('Metall einzahlen')).toHaveValue(3);
  });

  it('ruft contributeToBuilding mit den eingegebenen Mengen auf', async () => {
    const contribute = vi.mocked(buildingService.contributeToBuilding).mockResolvedValue({ justCompleted: false });
    const onChanged = vi.fn();

    render(
      <BuildingProgress building={building} resources={resources} isParent={true} onChanged={onChanged} />,
    );

    fireEvent.change(screen.getByLabelText('Holz einzahlen'), { target: { value: '10' } });
    fireEvent.click(screen.getByRole('button', { name: 'Einzahlen' }));

    await vi.waitFor(() => expect(contribute).toHaveBeenCalledWith(1, { wood: 10 }));
    await vi.waitFor(() => expect(onChanged).toHaveBeenCalled());
  });
});
