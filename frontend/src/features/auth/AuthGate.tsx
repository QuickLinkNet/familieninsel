import type { ReactNode } from 'react';
import { useAuth } from './AuthContext';
import { FamilyLoginScreen } from './FamilyLoginScreen';
import { ProfileSelectionScreen } from './ProfileSelectionScreen';
import { ParentPinDialog } from './ParentPinDialog';

export function AuthGate({ children }: { children: ReactNode }) {
  const { session, loading } = useAuth();

  if (loading) {
    return (
      <main className="auth-screen">
        <p className="loading-hint">Einen Moment ...</p>
      </main>
    );
  }

  if (session === null || !session.authenticated) {
    return <FamilyLoginScreen />;
  }

  if (session.playerId === null) {
    return <ProfileSelectionScreen />;
  }

  if (session.playerRole === 'parent' && !session.parentUnlocked) {
    return <ParentPinDialog />;
  }

  return <>{children}</>;
}
