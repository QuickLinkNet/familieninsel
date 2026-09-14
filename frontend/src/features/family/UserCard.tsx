import { useState } from 'react';
import type { ManagedPlayer } from '../../types/auth';
import { PlayerAvatar } from '../auth/PlayerAvatar';
import { EditNameAgeForm } from './EditNameAgeForm';
import { ParentPinControl } from './ParentPinControl';
import { ChildQrControl } from './ChildQrControl';
import * as playerService from '../../services/playerService';

interface UserCardProps {
  player: ManagedPlayer;
  isSelf: boolean;
  onChanged: () => void;
}

export function UserCard({ player, isSelf, onChanged }: UserCardProps) {
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function toggleActive(): Promise<void> {
    setBusy(true);
    setError(null);
    try {
      if (player.isActive) {
        await playerService.deactivatePlayer(player.id);
      } else {
        await playerService.activatePlayer(player.id);
      }
      onChanged();
    } catch {
      setError(
        player.isActive
          ? 'Konnte nicht deaktiviert werden (z. B. letzter aktiver Elternteil).'
          : 'Konnte nicht reaktiviert werden.',
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className={`user-card${player.isActive ? '' : ' user-card--inactive'}`}>
      <div className="user-card__header">
        <PlayerAvatar playerId={player.id} name={player.name} role={player.role} hasPhoto={player.hasPhoto} />
        <div className="user-card__identity">
          <strong>{player.name}</strong>
          <span className="user-card__meta">
            {player.role === 'parent' ? 'Elternteil' : 'Kind'}
            {player.age !== null && ` · ${player.age} Jahre`}
            {isSelf && ' · Du'}
            {!player.isActive && ' · Deaktiviert'}
          </span>
        </div>
        {!editing && (
          <button type="button" className="user-card__edit-toggle" onClick={() => setEditing(true)}>
            Bearbeiten
          </button>
        )}
      </div>

      {editing && (
        <EditNameAgeForm
          playerId={player.id}
          initialName={player.name}
          initialAge={player.age}
          onSaved={() => {
            setEditing(false);
            onChanged();
          }}
          onCancel={() => setEditing(false)}
        />
      )}

      {player.role === 'parent' && <ParentPinControl playerId={player.id} />}

      {player.role === 'child' && player.isActive && <ChildQrControl playerId={player.id} childName={player.name} />}
      {player.role === 'child' && !player.isActive && (
        <p className="user-card__hint">Deaktivierte Kinder können sich nicht mehr per QR-Code anmelden.</p>
      )}

      <div className="user-card__footer">
        <button type="button" className="auth-form__secondary" onClick={() => void toggleActive()} disabled={busy || (isSelf && player.isActive)}>
          {player.isActive ? 'Deaktivieren' : 'Reaktivieren'}
        </button>
        {isSelf && player.isActive && <span className="user-card__hint">Du kannst dich nicht selbst deaktivieren.</span>}
      </div>

      {error !== null && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
    </div>
  );
}
