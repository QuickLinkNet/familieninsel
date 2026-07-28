import { useState } from 'react';
import type { Building } from '../../types/building';
import type { Resource } from '../../types/resource';
import { contributeToBuilding } from '../../services/buildingService';
import { ApiError } from '../../types/api';

interface BuildingProgressProps {
  building: Building;
  resources: Resource[];
  isParent: boolean;
  onChanged: () => void;
}

const STAGE_LABELS: Record<number, string> = {
  1: 'Bauplatz',
  2: 'Fundament',
  3: 'Wände',
  4: 'Dach',
  5: 'Fertiggestellt',
};

export function BuildingProgress({ building, resources, isParent, onChanged }: BuildingProgressProps) {
  const [amounts, setAmounts] = useState<Record<string, number>>({});
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [celebration, setCelebration] = useState(false);

  const items = building.costs.map((cost) => {
    const resource = resources.find((candidate) => candidate.id === cost.resourceId);
    const remaining = Math.max(0, cost.required - cost.contributed);
    const maxInput = resource !== undefined ? Math.min(resource.amount, remaining) : 0;

    return { ...cost, resource, remaining, maxInput };
  });

  function updateAmount(resourceKey: string, value: string, max: number): void {
    const parsed = Math.max(0, Math.min(max, Number(value) || 0));
    setAmounts((previous) => ({ ...previous, [resourceKey]: parsed }));
  }

  function fillAvailable(): void {
    const next: Record<string, number> = {};
    for (const item of items) {
      if (item.resource === undefined || item.maxInput === 0) {
        continue;
      }
      next[item.resource.key] = item.maxInput;
    }
    setAmounts(next);
  }

  async function handleSubmit(): Promise<void> {
    setSubmitting(true);
    setError(null);
    try {
      const payload = Object.fromEntries(Object.entries(amounts).filter(([, value]) => value > 0));
      const result = await contributeToBuilding(building.id, payload);
      setAmounts({});
      if (result.justCompleted) {
        setCelebration(true);
      }
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Fehler beim Einzahlen.');
    } finally {
      setSubmitting(false);
    }
  }

  const hasAnyAmount = Object.values(amounts).some((value) => value > 0);
  const canContribute = isParent && building.status === 'in_progress';

  return (
    <section className="building-progress">
      <h2>{building.name}</h2>
      {building.status === 'completed' ? (
        <p className="building-complete">Fertiggestellt!</p>
      ) : (
        <p className="building-stage">Baustufe: {STAGE_LABELS[building.stage] ?? building.stage}</p>
      )}
      <div className="progress-bar">
        <div className="progress-bar__fill" style={{ width: `${building.progressPercent}%` }} />
      </div>
      <p className="progress-label">{building.progressPercent}% fertig</p>

      {celebration && (
        <p className="building-complete" role="status">
          Super gemacht! Die {building.name} ist fertig!
        </p>
      )}

      <ul className="building-costs">
        {items.map((item) => (
          <li key={item.resourceId}>
            <span>
              {item.resource?.name ?? '?'}: {item.contributed}/{item.required}
            </span>
            {canContribute && item.remaining > 0 && item.resource !== undefined && (
              <input
                type="number"
                min={0}
                max={item.maxInput}
                value={amounts[item.resource.key] ?? 0}
                onChange={(event) => updateAmount(item.resource!.key, event.target.value, item.maxInput)}
                aria-label={`${item.resource.name} einzahlen`}
              />
            )}
          </li>
        ))}
      </ul>

      {canContribute && (
        <div className="building-actions">
          <button type="button" onClick={fillAvailable}>
            Alle verfügbaren Rohstoffe einsetzen
          </button>
          <button
            type="button"
            disabled={submitting || !hasAnyAmount}
            onClick={() => {
              void handleSubmit();
            }}
          >
            Einzahlen
          </button>
        </div>
      )}

      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </section>
  );
}
