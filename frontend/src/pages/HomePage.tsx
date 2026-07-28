import { useEffect, useState } from 'react';
import { fetchHealth } from '../services/healthService';

type ConnectionState =
  | { kind: 'loading' }
  | { kind: 'connected'; time: string }
  | { kind: 'error'; message: string };

export function HomePage() {
  const [connection, setConnection] = useState<ConnectionState>({ kind: 'loading' });

  useEffect(() => {
    let cancelled = false;

    fetchHealth()
      .then((health) => {
        if (!cancelled) {
          setConnection({ kind: 'connected', time: health.time });
        }
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          const message = error instanceof Error ? error.message : 'Unbekannter Fehler';
          setConnection({ kind: 'error', message });
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <main className="home-page">
      <h1>Familien-Insel</h1>
      <p>Projektgrundlage steht. Das Spiel selbst folgt in den naechsten Phasen.</p>
      <section className="connection-status">
        {connection.kind === 'loading' && <p>Verbindung zum Backend wird geprueft...</p>}
        {connection.kind === 'connected' && (
          <p>Backend erreichbar (Serverzeit: {connection.time})</p>
        )}
        {connection.kind === 'error' && (
          <p role="alert">Backend nicht erreichbar: {connection.message}</p>
        )}
      </section>
    </main>
  );
}
