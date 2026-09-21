import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import type { ManagedPlayer } from '../../types/auth';
import * as playerService from '../../services/playerService';
import { resetFamilyProgress } from '../../services/resetService';
import { UserCard } from './UserCard';
import { AddChildForm } from './AddChildForm';
import './family-management.css';

export function FamilyManagementPage() {
  const { session, refreshPlayers } = useAuth();
  const [players, setPlayers] = useState<ManagedPlayer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [resetBusy, setResetBusy] = useState(false);
  const [resetError, setResetError] = useState<string | null>(null);
  const [resetDone, setResetDone] = useState(false);

  async function handleResetFamilyProgress(): Promise<void> {
    const confirmed = window.confirm(
      'Wirklich den GESAMTEN Fortschritt der Familie zurücksetzen?\n\n' +
        'Alle Aufgaben werden wieder offen, alle Rohstoffe geleert, das Bauprojekt beginnt neu bei der ' +
        'Strandhütte (Stufe 1), Minispiel-Freischaltungen und das Tagebuch werden gelöscht.\n\n' +
        'Profile, PINs und QR-Codes bleiben erhalten. Das kann nicht rückgängig gemacht werden.',
    );
    if (!confirmed) {
      return;
    }

    setResetBusy(true);
    setResetError(null);
    setResetDone(false);
    try {
      await resetFamilyProgress();
      setResetDone(true);
    } catch {
      setResetError('Zurücksetzen fehlgeschlagen.');
    } finally {
      setResetBusy(false);
    }
  }

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

          <section className="family-management__danger-zone">
            <h2>Fortschritt & Testen</h2>
            <p className="family-management__intro">
              Für die einzelnen Kinder gibt es Reset-Optionen direkt bei ihrer Karte oben (Intro, Belohnungen,
              eigene Aufgaben). Hier der große Knopf für die ganze Familie auf einmal:
            </p>
            <button
              type="button"
              className="family-management__reset-button"
              disabled={resetBusy}
              onClick={() => {
                void handleResetFamilyProgress();
              }}
            >
              Gesamten Fortschritt zurücksetzen
            </button>
            {resetDone && <p className="child-reset-control__success">Fortschritt wurde zurückgesetzt.</p>}
            {resetError !== null && (
              <p role="alert" className="auth-error">
                {resetError}
              </p>
            )}
          </section>
        </>
      )}
    </main>
  );
}
