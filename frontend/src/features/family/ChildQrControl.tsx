import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import * as playerService from '../../services/playerService';

interface ChildQrControlProps {
  playerId: number;
  childName: string;
}

function buildLoginUrl(token: string): string {
  const base = import.meta.env.BASE_URL;
  return `${window.location.origin}${base}kind/${token}`;
}

export function ChildQrControl({ playerId, childName }: ChildQrControlProps) {
  const [status, setStatus] = useState<playerService.LoginTokenStatus | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [loginUrl, setLoginUrl] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    playerService
      .fetchLoginTokenStatus(playerId)
      .then(setStatus)
      .catch(() => setError('Status konnte nicht geladen werden.'));
  }, [playerId]);

  async function handleGenerate(): Promise<void> {
    setBusy(true);
    setError(null);
    try {
      const token = await playerService.regenerateLoginToken(playerId);
      const url = buildLoginUrl(token);
      setLoginUrl(url);
      setQrDataUrl(await QRCode.toDataURL(url, { width: 220, margin: 1 }));
      setStatus(await playerService.fetchLoginTokenStatus(playerId));
    } catch {
      setError('QR-Code konnte nicht erzeugt werden.');
    } finally {
      setBusy(false);
    }
  }

  async function handleRevoke(): Promise<void> {
    setBusy(true);
    setError(null);
    try {
      await playerService.revokeLoginToken(playerId);
      setQrDataUrl(null);
      setLoginUrl(null);
      setStatus(await playerService.fetchLoginTokenStatus(playerId));
    } catch {
      setError('Code konnte nicht widerrufen werden.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="child-qr-control">
      {status !== null && (
        <p className="child-qr-control__status">
          {status.active
            ? status.lastUsedAt !== null
              ? `QR-Code aktiv, zuletzt benutzt am ${new Date(status.lastUsedAt).toLocaleString('de-DE')}`
              : 'QR-Code aktiv, wurde noch nicht gescannt'
            : 'Noch kein QR-Code erstellt'}
        </p>
      )}

      {qrDataUrl !== null && loginUrl !== null && (
        <div className="child-qr-control__qr">
          <img src={qrDataUrl} alt={`QR-Code für ${childName}`} width={180} height={180} />
          <p className="child-qr-control__url">{loginUrl}</p>
        </div>
      )}

      {qrDataUrl === null && status?.active === true && (
        <p className="child-qr-control__hint">
          Der Code ist gültig, wird aber aus Sicherheitsgründen nicht gespeichert und lässt sich nach dem Neuladen
          der Seite nicht erneut anzeigen. Einfach "Neuen QR-Code erzeugen" klicken, um ihn nochmal zu sehen – das
          ist unbedenklich, solange er noch nicht gescannt wurde.
        </p>
      )}

      <div className="child-qr-control__actions">
        <button type="button" onClick={() => void handleGenerate()} disabled={busy}>
          {status?.active === true ? 'Neuen QR-Code erzeugen' : 'QR-Code erstellen'}
        </button>
        {status?.active === true && (
          <button type="button" className="auth-form__secondary" onClick={() => void handleRevoke()} disabled={busy}>
            Widerrufen
          </button>
        )}
      </div>

      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </div>
  );
}
