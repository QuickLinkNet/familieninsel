import { useAuth } from '../features/auth/AuthContext';
import { useTasksAndResources } from '../hooks/useTasksAndResources';
import { useBuilding } from '../hooks/useBuilding';
import { useActivity } from '../hooks/useActivity';
import { ResourceBar } from '../features/resources/ResourceBar';
import { ChildTaskList } from '../features/tasks/ChildTaskList';
import { ParentTaskDashboard } from '../features/tasks/ParentTaskDashboard';
import { BuildingProgress } from '../features/island/BuildingProgress';
import { ActivityFeed } from '../features/island/ActivityFeed';

export function HomePage() {
  const { session, players, logout } = useAuth();
  const { tasks, resources, loading, error, refresh } = useTasksAndResources();
  const { building, loading: buildingLoading, error: buildingError, refresh: refreshBuilding } = useBuilding();
  const { entries, refresh: refreshActivity } = useActivity();
  const currentPlayer = players.find((player) => player.id === session?.playerId) ?? null;

  async function refreshAll(): Promise<void> {
    await Promise.all([refresh(), refreshBuilding(), refreshActivity()]);
  }

  return (
    <main className="home-page">
      <header className="home-header">
        <h1>Familien-Insel</h1>
        {currentPlayer !== null && (
          <div className="current-player">
            <span>
              Angemeldet als <strong>{currentPlayer.name}</strong>
              {currentPlayer.role === 'parent' ? ' (Elternteil)' : ''}
            </span>
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

      <ResourceBar resources={resources} />

      {!buildingLoading && building !== null && (
        <BuildingProgress
          building={building}
          resources={resources}
          isParent={session?.playerRole === 'parent'}
          onChanged={() => {
            void refreshAll();
          }}
        />
      )}
      {buildingError !== null && (
        <p role="alert" className="auth-error">
          {buildingError}
        </p>
      )}

      {loading && <p>Lade Aufgaben...</p>}
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

      <ActivityFeed entries={entries} />
    </main>
  );
}
