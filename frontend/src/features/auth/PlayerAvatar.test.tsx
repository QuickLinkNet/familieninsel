import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PlayerAvatar } from './PlayerAvatar';

describe('PlayerAvatar', () => {
  it('zeigt den Anfangsbuchstaben, wenn kein Foto vorhanden ist', () => {
    render(<PlayerAvatar playerId={3} name="Emil" role="child" hasPhoto={false} />);

    expect(screen.getByText('E')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('zeigt das Foto, wenn hasPhoto true ist', () => {
    render(<PlayerAvatar playerId={3} name="Emil" role="child" hasPhoto={true} />);

    const image = screen.getByRole('img', { name: 'Foto von Emil' });
    expect(image.getAttribute('src')).toMatch(/\/players\/3\/photo$/);
  });

  it('haengt einen Cache-Bust-Parameter an, wenn angegeben', () => {
    render(<PlayerAvatar playerId={3} name="Emil" role="child" hasPhoto={true} cacheBust={7} />);

    const image = screen.getByRole('img', { name: 'Foto von Emil' });
    expect(image.getAttribute('src')).toMatch(/\/players\/3\/photo\?v=7$/);
  });
});
