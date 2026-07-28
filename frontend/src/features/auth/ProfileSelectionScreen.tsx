import { useAuth } from './AuthContext';

export function ProfileSelectionScreen() {
  const { players, chooseProfile, error, loading } = useAuth();

  return (
    <main className="auth-screen">
      <h1>Wer bist du?</h1>
      <div className="profile-grid">
        {players.map((player) => (
          <button
            key={player.id}
            type="button"
            className="profile-card"
            onClick={() => {
              void chooseProfile(player.id);
            }}
            disabled={loading}
          >
            <span className="profile-avatar" aria-hidden="true">
              {player.name.charAt(0)}
            </span>
            <span className="profile-name">{player.name}</span>
            {player.age !== null && <span className="profile-age">{player.age} Jahre</span>}
            {player.role === 'parent' && <span className="profile-badge">Elternteil</span>}
          </button>
        ))}
      </div>
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </main>
  );
}
