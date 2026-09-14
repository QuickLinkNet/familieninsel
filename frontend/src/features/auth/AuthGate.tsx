import type { ReactNode } from 'react';
import { useAuth } from './AuthContext';
import { ParentLoginScreen } from './ParentLoginScreen';

export function AuthGate({ children }: { children: ReactNode }) {
  const { session, loading } = useAuth();

  if (loading) {
    return (
      <main className="auth-screen">
        <div className="auth-panel">
          <p className="loading-hint">Einen Moment ...</p>
        </div>
      </main>
    );
  }

  if (session === null || !session.authenticated) {
    return <ParentLoginScreen />;
  }

  return <>{children}</>;
}
