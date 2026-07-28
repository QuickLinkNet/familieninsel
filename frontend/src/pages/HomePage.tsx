import { useAuth } from '../features/auth/AuthContext';

export function HomePage() {
  const { session, players, logout } = useAuth();
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
      <p>Die Insel und das Aufgabensystem folgen in den naechsten Phasen.</p>
    </main>
  );
}
