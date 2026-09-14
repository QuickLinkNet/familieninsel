import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import type { ManagedPlayer } from '../../types/auth';
import * as playerService from '../../services/playerService';
import { UserCard } from './UserCard';
import { AddChildForm } from './AddChildForm';
import './family-management.css';

export function FamilyManagementPage() {
  const { session, refreshPlayers } = useAuth();
  const [players, setPlayers] = useState<ManagedPlayer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setPlayers(await playerService.fetchAllPlayers());
      setError(null);
    } catch {
      setError('Familienmitglieder konnten nicht geladen werden.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function handleChanged(): void {
    void load();
    void refreshPlayers();
  }

  if (session?.playerRole !== 'parent') {
    return (
      <main className="auth-screen">
        <h1>Benutzerverwaltung</h1>
        <p>Diese Seite ist nur für Eltern verfügbar.</p>
        <Link to="/">Zurück zur Insel</Link>
      </main>
    );
  }

  const parents = players.filter((player) => player.role === 'parent');
  const children = players.filter((player) => player.role === 'child');

  return (
    <main className="family-management">
      <header className="family-management__header">
        <h1>Benutzerverwaltung</h1>
        <Link to="/">Zurück zur Insel</Link>
      </header>

      <p className="family-management__intro">
        Hier verwaltet ihr die ganze Familie: Namen und Alter, Eltern-Passwörter und die QR-Codes, mit denen sich
        Kinder auf ihrem eigenen Tablet anmelden.
      </p>

      {loading && <p className="loading-hint">Lade Familienmitglieder ...</p>}
      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}

      {!loading && (
        <>
          <section>
            <h2>Eltern</h2>
            <div className="family-management__cards">
              {parents.map((player) => (
                <UserCard
                  key={player.id}
                  player={player}
                  isSelf={player.id === session.playerId}
                  onChanged={handleChanged}
                />
              ))}
            </div>
          </section>

          <section>
            <h2>Kinder</h2>
            <div className="family-management__cards">
              {children.map((player) => (
                <UserCard
                  key={player.id}
                  player={player}
                  isSelf={player.id === session.playerId}
                  onChanged={handleChanged}
                />
              ))}
            </div>
            <h3>Neues Kind anlegen</h3>
            <AddChildForm onAdded={handleChanged} />
          </section>
        </>
      )}
    </main>
  );
}
