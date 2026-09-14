import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { ApiError } from '../../types/api';
import { uploadPlayerPhoto } from '../../services/photoService';

interface PhotoUploadControlProps {
  playerId: number;
  onUploaded: () => void;
}

export function PhotoUploadControl({ playerId, onUploaded }: PhotoUploadControlProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleFileChange(event: ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';
    if (file === null) {
      return;
    }

    setUploading(true);
    setError(null);
    try {
      await uploadPlayerPhoto(playerId, file);
      onUploaded();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Foto konnte nicht hochgeladen werden.');
    } finally {
      setUploading(false);
    }
  }

  return (
    <span className="photo-upload-control">
      <button
        type="button"
        className="photo-upload-button"
        onClick={() => inputRef.current?.click()}
        disabled={uploading}
      >
        {uploading ? 'Lade hoch ...' : 'Foto ändern'}
      </button>
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        capture="user"
        hidden
        onChange={(event) => {
          void handleFileChange(event);
        }}
      />
      {error !== null && (
        <span role="alert" className="auth-error">
          {error}
        </span>
      )}
    </span>
  );
}
