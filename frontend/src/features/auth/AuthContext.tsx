import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import type { Player, SessionState } from '../../types/auth';
import { ApiError } from '../../types/api';
import * as authService from '../../services/authService';

interface AuthContextValue {
  session: SessionState | null;
  players: Player[];
  loading: boolean;
  error: string | null;
  loginWithFamilyCode: (code: string) => Promise<boolean>;
  chooseProfile: (playerId: number) => Promise<boolean>;
  unlockParent: (pin: string) => Promise<boolean>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function messageFor(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Verbindung zum Server fehlgeschlagen.';
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<SessionState | null>(null);
  const [players, setPlayers] = useState<Player[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    const nextSession = await authService.fetchSession();
    setSession(nextSession);
    setPlayers(nextSession.authenticated ? await authService.fetchPlayers() : []);
    return nextSession;
  }, []);

  useEffect(() => {
    refresh()
      .catch((err: unknown) => setError(messageFor(err)))
      .finally(() => setLoading(false));
  }, [refresh]);

  const loginWithFamilyCode = useCallback(
    async (code: string) => {
      setError(null);
      try {
        await authService.familyLogin(code);
        await refresh();
        return true;
      } catch (err) {
        setError(messageFor(err));
        return false;
      }
    },
    [refresh],
  );

  const chooseProfile = useCallback(
    async (playerId: number) => {
      setError(null);
      try {
        await authService.selectProfile(playerId);
        await refresh();
        return true;
      } catch (err) {
        setError(messageFor(err));
        return false;
      }
    },
    [refresh],
  );

  const unlockParent = useCallback(
    async (pin: string) => {
      setError(null);
      try {
        await authService.unlockParent(pin);
        await refresh();
        return true;
      } catch (err) {
        setError(messageFor(err));
        return false;
      }
    },
    [refresh],
  );

  const logout = useCallback(async () => {
    await authService.logout();
    await refresh();
  }, [refresh]);

  const value = useMemo<AuthContextValue>(
    () => ({ session, players, loading, error, loginWithFamilyCode, chooseProfile, unlockParent, logout }),
    [session, players, loading, error, loginWithFamilyCode, chooseProfile, unlockParent, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (context === null) {
    throw new Error('useAuth muss innerhalb von AuthProvider verwendet werden.');
  }

  return context;
}
