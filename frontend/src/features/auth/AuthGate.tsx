import type { ReactNode } from 'react';
import { useAuth } from './AuthContext';
import { ParentLoginScreen } from './ParentLoginScreen';
import compassIcon from '../../assets/island/icon-kompass.webp';

export function AuthGate({ children }: { children: ReactNode }) {
  const { session, loading } = useAuth();

  if (loading) {
    return (
      <main className="auth-screen">
        <div className="loading-hint">
          <img src={compassIcon} alt="" aria-hidden="true" className="loading-hint__compass" />
          <p>Die Insel wacht auf ...</p>
        </div>
      </main>
    );
  }

  if (session === null || !session.authenticated) {
    return <ParentLoginScreen />;
  }

  return <>{children}</>;
}
