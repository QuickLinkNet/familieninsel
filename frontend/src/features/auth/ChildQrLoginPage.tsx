import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from './AuthContext';

type LoginStatus = 'pending' | 'success' | 'error';

export function ChildQrLoginPage() {
  const { token } = useParams<{ token: string }>();
  const { loginWithQrToken, error } = useAuth();
  const navigate = useNavigate();
  const [status, setStatus] = useState<LoginStatus>('pending');
  const attempted = useRef(false);

  useEffect(() => {
    if (attempted.current || token === undefined) {
      return;
    }
    attempted.current = true;

    void loginWithQrToken(token).then((success) => {
      if (success) {
        setStatus('success');
        navigate('/', { replace: true });
      } else {
        setStatus('error');
      }
    });
  }, [token, loginWithQrToken, navigate]);

  return (
    <main className="auth-screen">
      <div className="auth-panel">
        <h1>Familien-Insel</h1>
        {status === 'pending' && <p className="loading-hint">Du wirst angemeldet ...</p>}
        {status === 'error' && (
          <p role="alert" className="auth-error">
            {error ?? 'Dieser QR-Code ist ungültig oder wurde bereits ersetzt. Bitte einen Elternteil um einen neuen Code bitten.'}
          </p>
        )}
      </div>
    </main>
  );
}
