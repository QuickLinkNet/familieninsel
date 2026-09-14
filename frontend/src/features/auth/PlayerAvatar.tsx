import type { PlayerRole } from '../../types/auth';
import { photoUrl } from '../../services/photoService';
import rahmenErwachsen from '../../assets/island/rahmen-erwachsen.webp';
import rahmenKind from '../../assets/island/rahmen-kind.webp';
import './player-avatar.css';

interface PlayerAvatarProps {
  playerId: number;
  name: string;
  role: PlayerRole;
  hasPhoto: boolean;
  cacheBust?: number;
  size?: number;
}

export function PlayerAvatar({ playerId, name, role, hasPhoto, cacheBust, size }: PlayerAvatarProps) {
  const frame = role === 'parent' ? rahmenErwachsen : rahmenKind;
  const style = size !== undefined ? { width: size, height: size } : undefined;

  return (
    <span className="player-avatar-frame" style={style}>
      {hasPhoto ? (
        <img
          src={cacheBust !== undefined ? `${photoUrl(playerId)}?v=${cacheBust}` : photoUrl(playerId)}
          alt={`Foto von ${name}`}
          className="player-avatar-frame__photo"
        />
      ) : (
        <span className="player-avatar-frame__photo player-avatar-frame__photo--placeholder" aria-hidden="true">
          {name.charAt(0)}
        </span>
      )}
      <img src={frame} alt="" aria-hidden="true" className="player-avatar-frame__ring" />
    </span>
  );
}
