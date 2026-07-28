import { useAuth } from '../features/auth/AuthContext';
import { useTasksAndResources } from '../hooks/useTasksAndResources';
import { ResourceBar } from '../features/resources/ResourceBar';
import { ChildTaskList } from '../features/tasks/ChildTaskList';
import { ParentTaskDashboard } from '../features/tasks/ParentTaskDashboard';

export function HomePage() {
  const { session, players, logout } = useAuth();
  const { tasks, resources, loading, error, refresh } = useTasksAndResources();
  const currentPlayer = players.find((player) => player.id === session?.playerId) ?? null;

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

      {loading && <p>Lade Aufgaben...</p>}
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}

      {!loading && session?.playerRole === 'child' && (
        <ChildTaskList tasks={tasks} resources={resources} onChanged={() => void refresh()} />
      )}

      {!loading && session?.playerRole === 'parent' && (
        <ParentTaskDashboard
          tasks={tasks}
          resources={resources}
          players={players}
          onChanged={() => void refresh()}
        />
      )}
    </main>
  );
}
