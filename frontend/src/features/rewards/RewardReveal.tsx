import { useEffect, useRef, useState } from 'react';
import type { Resource } from '../../types/resource';
import type { RewardEvent } from '../../types/reward';
import { ResourceIcon } from '../resources/ResourceIcon';
import { stageImageSrc } from '../island/BuildingSprite';
import parrotIcon from '../../assets/island/icon-papagei.webp';
import './reward-reveal.css';

interface RewardRevealProps {
  events: RewardEvent[];
  resources: Resource[];
  onFinished: () => void;
}

const TRANSITION_MS = 250;
const PROGRESS_ANIMATION_DELAY_MS = 150;

interface AggregatedReward {
  resourceKey: string;
  amount: number;
}

interface BuildingSummary {
  key: string;
  name: string;
  beforePercent: number;
  afterPercent: number;
  beforeStage: number;
  afterStage: number;
  justCompleted: boolean;
  celebrate: boolean;
  unlockedMinigameName: string | null;
  unlockedBuildingName: string | null;
}

function summarizeTitle(events: RewardEvent[]): string {
  const titles = events.map((event) => event.taskTitle);
  if (titles.length === 1) {
    return `„${titles[0]}“ ist geschafft!`;
  }
  if (titles.length <= 3) {
    return `${titles.map((title) => `„${title}“`).join(', ')} sind geschafft!`;
  }
  return `${titles.length} Aufgaben sind geschafft!`;
}

function aggregateRewards(events: RewardEvent[]): AggregatedReward[] {
  const totals = new Map<string, number>();
  for (const event of events) {
    for (const reward of event.rewards) {
      totals.set(reward.resourceKey, (totals.get(reward.resourceKey) ?? 0) + reward.amount);
    }
  }
  return Array.from(totals.entries()).map(([resourceKey, amount]) => ({ resourceKey, amount }));
}

function summarizeBuilding(events: RewardEvent[]): BuildingSummary | null {
  const withBuilding = events.filter((event) => event.building !== null);
  if (withBuilding.length === 0) {
    return null;
  }

  const first = withBuilding[0].building!;
  const last = withBuilding[withBuilding.length - 1].building!;
  const justCompleted = withBuilding.some((event) => event.building!.justCompleted);
  const celebrate = justCompleted || withBuilding.some((event) => event.building!.afterStage > event.building!.beforeStage);
  const unlockedMinigameName = withBuilding.map((event) => event.building!.unlockedMinigameName).find(Boolean) ?? null;
  const unlockedBuildingName = withBuilding.map((event) => event.building!.unlockedBuildingName).find(Boolean) ?? null;

  return {
    key: last.key,
    name: last.name,
    beforePercent: first.beforePercent,
    afterPercent: last.afterPercent,
    beforeStage: first.beforeStage,
    afterStage: last.afterStage,
    justCompleted,
    celebrate,
    unlockedMinigameName,
    unlockedBuildingName,
  };
}

export function RewardReveal({ events, resources, onFinished }: RewardRevealProps) {
  const building = summarizeBuilding(events);
  const slideCount = building !== null ? 3 : 2;
  const [slideIndex, setSlideIndex] = useState(0);
  const [leaving, setLeaving] = useState(false);
  const [animatedPercent, setAnimatedPercent] = useState(building?.beforePercent ?? 0);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (timer.current !== null) {
        clearTimeout(timer.current);
      }
    };
  }, []);

  useEffect(() => {
    if (slideIndex !== 2 || building === null) {
      return;
    }
    const raf = setTimeout(() => setAnimatedPercent(building.afterPercent), PROGRESS_ANIMATION_DELAY_MS);
    return () => clearTimeout(raf);
  }, [slideIndex, building]);

  const isLastSlide = slideIndex === slideCount - 1;

  function goNext(): void {
    if (isLastSlide) {
      onFinished();
      return;
    }
    setLeaving(true);
    timer.current = setTimeout(() => {
      setSlideIndex((current) => current + 1);
      setLeaving(false);
    }, TRANSITION_MS);
  }

  function resourceName(key: string): string {
    return resources.find((resource) => resource.key === key)?.name ?? key;
  }

  return (
    <div className="reward-reveal">
      <div className={`reward-reveal__panel${leaving ? ' reward-reveal__panel--leaving' : ''}`}>
        {slideIndex === 0 && (
          <>
            <img src={parrotIcon} alt="" aria-hidden="true" className="reward-reveal__pico" />
            <p className="reward-reveal__headline">Deine Aufgabe hat unserer Insel geholfen!</p>
            <p className="reward-reveal__text">{summarizeTitle(events)}</p>
          </>
        )}

        {slideIndex === 1 && (
          <>
            <p className="reward-reveal__headline">Das habt ihr verdient:</p>
            <div className="reward-reveal__rewards">
              {aggregateRewards(events).map((reward) => (
                <div key={reward.resourceKey} className="reward-reveal__reward-item">
                  <ResourceIcon resourceKey={reward.resourceKey} className="reward-reveal__reward-icon" />
                  <span>
                    +{reward.amount} {resourceName(reward.resourceKey)}
                  </span>
                </div>
              ))}
            </div>
          </>
        )}

        {slideIndex === 2 && building !== null && (
          <div className={building.celebrate ? 'reward-reveal__celebrate' : undefined}>
            <img
              src={stageImageSrc(building.key, building.afterStage)}
              alt=""
              aria-hidden="true"
              className="reward-reveal__building-image"
            />
            <p className="reward-reveal__headline">
              {building.justCompleted ? `${building.name} ist fertig! 🎉` : `${building.name} macht Fortschritte!`}
            </p>
            <div className="progress-bar reward-reveal__progress-bar">
              <div className="progress-bar__fill" style={{ width: `${animatedPercent}%` }} />
            </div>
            <p className="reward-reveal__text">{building.afterPercent}% fertig</p>
            {building.unlockedMinigameName !== null && (
              <p className="reward-reveal__unlock">Neu freigeschaltet: {building.unlockedMinigameName}!</p>
            )}
            {building.unlockedBuildingName !== null && (
              <p className="reward-reveal__unlock">Neues Bauprojekt: {building.unlockedBuildingName}!</p>
            )}
          </div>
        )}

        <button type="button" className="reward-reveal__next-button" onClick={goNext}>
          {isLastSlide ? 'Weiter zur Insel' : 'Weiter'}
        </button>

        <div className="reward-reveal__dots">
          {Array.from({ length: slideCount }, (_, index) => (
            <span
              key={index}
              className={`reward-reveal__dot${index === slideIndex ? ' reward-reveal__dot--active' : ''}`}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
