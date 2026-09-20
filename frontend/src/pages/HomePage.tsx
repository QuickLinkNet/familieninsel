import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../features/auth/AuthContext';
import { PlayerAvatar } from '../features/auth/PlayerAvatar';
import { PhotoUploadControl } from '../features/auth/PhotoUploadControl';
import { useTasksAndResources } from '../hooks/useTasksAndResources';
import { useBuildings } from '../hooks/useBuildings';
import { useActivity } from '../hooks/useActivity';
import { useMinigames } from '../hooks/useMinigames';
import { ResourceBar } from '../features/resources/ResourceBar';
import { ChildTaskList } from '../features/tasks/ChildTaskList';
import { ParentTaskDashboard } from '../features/tasks/ParentTaskDashboard';
import { BuildingProgress } from '../features/island/BuildingProgress';
import { ActivityFeed } from '../features/island/ActivityFeed';
import { MinigameSection } from '../features/minigames/MinigameSection';
import { IslandMap } from '../features/island/IslandMap';
import { DashboardOverlay } from '../features/island/DashboardOverlay';
import { IntroStory } from '../features/onboarding/IntroStory';
import { OnboardingSpotlight } from '../features/onboarding/OnboardingSpotlight';
import { markIntroSeen } from '../services/playerService';

export function HomePage() {
  const { session, players, logout, refreshPlayers } = useAuth();
  const [photoVersion, setPhotoVersion] = useState(0);
  const [overlayOpen, setOverlayOpen] = useState(false);
  const [showFirstTaskHint, setShowFirstTaskHint] = useState(false);
  const { tasks, resources, loading, error, refresh } = useTasksAndResources();
  const { buildings, loading: buildingsLoading, error: buildingsError, refresh: refreshBuildings } = useBuildings();
  const { entries, refresh: refreshActivity } = useActivity();
  const { minigames, loading: minigamesLoading, refresh: refreshMinigames } = useMinigames();
  const currentPlayer = players.find((player) => player.id === session?.playerId) ?? null;

  // Ein Kind, das sein Story-Intro noch nicht gesehen hat, bekommt es vor
  // dem normalen Dashboard gezeigt - siehe features/onboarding/IntroStory.
  const showIntro =
    session?.playerRole === 'child' && currentPlayer !== null && currentPlayer.introSeenAt === null;

  async function refreshAll(): Promise<void> {
    await Promise.all([refresh(), refreshBuildings(), refreshActivity(), refreshMinigames()]);
  }

  async function handleIntroFinished(): Promise<void> {
    if (currentPlayer !== null) {
      try {
        await markIntroSeen(currentPlayer.id);
      } catch {
        // Kein Blocker: das Kind soll trotzdem weiterspielen koennen, auch
        // wenn das Merken fehlschlaegt - dann sieht es das Intro beim
        // naechsten Login halt nochmal.
      }
      await refreshPlayers();
    }
    setOverlayOpen(true);
    setShowFirstTaskHint(true);
  }

  return (
    <main className="home-page">
      <IslandMap buildings={buildings} />

      <header className="home-header home-header--floating">
        <h1>Familien-Insel</h1>
        {currentPlayer !== null && (
          <div className="current-player">
            <PlayerAvatar
              playerId={currentPlayer.id}
              name={currentPlayer.name}
              role={currentPlayer.role}
              hasPhoto={currentPlayer.hasPhoto}
              cacheBust={photoVersion}
            />
            <span className="current-player__label">
              Angemeldet als <strong>{currentPlayer.name}</strong>
              {currentPlayer.role === 'parent' ? ' (Elternteil)' : ''}
            </span>
            <PhotoUploadControl
              playerId={currentPlayer.id}
              onUploaded={() => {
                setPhotoVersion((version) => version + 1);
                void refreshPlayers();
              }}
            />
            {session?.playerRole === 'parent' && <Link to="/familie">Familie verwalten</Link>}
            <button
              type="button"
              onClick={() => {
                void logout();
              }}
            >
              Abmelden
            </button>
          </div>
        )}
      </header>

      <DashboardOverlay isOpen={overlayOpen} onToggle={() => setOverlayOpen((open) => !open)}>
        <ResourceBar resources={resources} />

        {/* Aufgaben zuerst: Kinder sollen ihre Aufgabe ohne Scrollen finden,
            Eltern sollen offene Bestaetigungen sofort sehen. */}
        {loading && <p className="loading-hint">Lade Aufgaben ...</p>}
        {error !== null && (
          <p role="alert" className="auth-error">
            {error}
          </p>
        )}

        {!loading && session?.playerRole === 'child' && (
          <ChildTaskList
            tasks={tasks}
            resources={resources}
            onChanged={() => {
              void refreshAll();
            }}
          />
        )}

        {!loading && session?.playerRole === 'parent' && (
          <ParentTaskDashboard
            tasks={tasks}
            resources={resources}
            players={players}
            onChanged={() => {
              void refreshAll();
            }}
          />
        )}

        {!buildingsLoading &&
          buildings.map((building) => (
            <BuildingProgress
              key={building.id}
              building={building}
              resources={resources}
              isParent={session?.playerRole === 'parent'}
              onChanged={() => {
                void refreshAll();
              }}
            />
          ))}
        {buildingsError !== null && (
          <p role="alert" className="auth-error">
            {buildingsError}
          </p>
        )}

        {!minigamesLoading && (
          <MinigameSection
            minigames={minigames}
            onChanged={() => {
              void refreshAll();
            }}
          />
        )}

        <ActivityFeed entries={entries} />
      </DashboardOverlay>

      {showIntro && (
        <IntroStory
          onFinished={() => {
            void handleIntroFinished();
          }}
        />
      )}

      {showFirstTaskHint && !showIntro && (
        <OnboardingSpotlight
          targetSelector=".task-card"
          message="Das ist deine Aufgabe! Wenn du fertig bist, tippe auf 'Erledigt!'"
          onDismiss={() => setShowFirstTaskHint(false)}
        />
      )}
    </main>
  );
}
