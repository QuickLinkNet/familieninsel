import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import type { ParentCandidate, Player, SessionState } from '../../types/auth';
import { ApiError } from '../../types/api';
import * as authService from '../../services/authService';

interface AuthContextValue {
  session: SessionState | null;
  players: Player[];
  parentCandidates: ParentCandidate[];
  loading: boolean;
  error: string | null;
  loadParentCandidates: () => Promise<void>;
  verifyParentPin: (playerId: number, pin: string) => Promise<boolean>;
  completeLogin: () => Promise<void>;
  loginWithQrToken: (token: string) => Promise<boolean>;
  logout: () => Promise<void>;
  refreshPlayers: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function messageFor(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Verbindung zum Server fehlgeschlagen.';
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<SessionState | null>(null);
  const [players, setPlayers] = useState<Player[]>([]);
  const [parentCandidates, setParentCandidates] = useState<ParentCandidate[]>([]);
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

  const loadParentCandidates = useCallback(async () => {
    try {
      setParentCandidates(await authService.fetchParentCandidates());
    } catch (err) {
      setError(messageFor(err));
    }
  }, []);

  /**
   * Prueft die PIN und meldet den Elternteil server-seitig an, loest aber
   * bewusst noch KEIN refresh() aus - der PIN-Screen zeigt danach erst kurz
   * "Willkommen zurueck" an, bevor completeLogin() den eigentlichen
   * Uebergang zur App ausloest (siehe ParentLoginScreen).
   */
  const verifyParentPin = useCallback(async (playerId: number, pin: string) => {
    setError(null);
    try {
      await authService.parentLogin(playerId, pin);
      return true;
    } catch (err) {
      setError(messageFor(err));
      return false;
    }
  }, []);

  const completeLogin = useCallback(async () => {
    await refresh();
  }, [refresh]);

  const loginWithQrToken = useCallback(
    async (token: string) => {
      setError(null);
      try {
        await authService.qrLogin(token);
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

  const refreshPlayers = useCallback(async () => {
    setPlayers(await authService.fetchPlayers());
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      session,
      players,
      parentCandidates,
      loading,
      error,
      loadParentCandidates,
      verifyParentPin,
      completeLogin,
      loginWithQrToken,
      logout,
      refreshPlayers,
    }),
    [
      session,
      players,
      parentCandidates,
      loading,
      error,
      loadParentCandidates,
      verifyParentPin,
      completeLogin,
      loginWithQrToken,
      logout,
      refreshPlayers,
    ],
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
