import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PhotoUploadControl } from './PhotoUploadControl';
import * as photoService from '../../services/photoService';
import { ApiError } from '../../types/api';

vi.mock('../../services/photoService');

function selectFile(input: HTMLElement, file: File): void {
  fireEvent.change(input, { target: { files: [file] } });
}

describe('PhotoUploadControl', () => {
  it('laedt eine ausgewaehlte Datei hoch und ruft onUploaded auf', async () => {
    const uploadPlayerPhoto = vi.mocked(photoService.uploadPlayerPhoto).mockResolvedValue(undefined);
    const onUploaded = vi.fn();

    const { container } = render(<PhotoUploadControl playerId={3} onUploaded={onUploaded} />);
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    const file = new File(['foto'], 'foto.jpg', { type: 'image/jpeg' });

    selectFile(input, file);

    await waitFor(() => expect(uploadPlayerPhoto).toHaveBeenCalledWith(3, file));
    await waitFor(() => expect(onUploaded).toHaveBeenCalled());
  });

  it('zeigt eine Fehlermeldung, wenn der Upload fehlschlaegt', async () => {
    vi.mocked(photoService.uploadPlayerPhoto).mockRejectedValue(
      new ApiError('FILE_TOO_LARGE', 'Das Bild darf hoechstens 5 MB gross sein.'),
    );

    const { container } = render(<PhotoUploadControl playerId={3} onUploaded={vi.fn()} />);
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    const file = new File(['foto'], 'foto.jpg', { type: 'image/jpeg' });

    selectFile(input, file);

    expect(await screen.findByRole('alert')).toHaveTextContent('Das Bild darf hoechstens 5 MB gross sein.');
  });
});
