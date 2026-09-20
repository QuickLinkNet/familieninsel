import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { IntroStory } from './IntroStory';

describe('IntroStory', () => {
  it('zeigt das erste Slide und schaltet ueber "Weiter" durch alle Slides', async () => {
    render(<IntroStory onFinished={vi.fn()} />);

    expect(screen.getByText('Ein wilder Sturm hat euer Boot erwischt!')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() =>
      expect(
        screen.getByText('Aber alle sind wohlauf! Ihr seid an einem geheimnisvollen Strand gestrandet.'),
      ).toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() =>
      expect(screen.getByText('Hallo! Ich bin Pico. Ich zeige dir, was wir tun müssen!')).toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() =>
      expect(
        screen.getByText('Zusammen bauen wir eine Strandhütte! Dafür brauchen wir Holz, Metall, Stoff und Seile.'),
      ).toBeInTheDocument(),
    );

    expect(screen.getByRole('button', { name: 'Los geht’s!' })).toBeInTheDocument();
  });

  it('ruft onFinished beim letzten Slide auf', async () => {
    const onFinished = vi.fn();
    render(<IntroStory onFinished={onFinished} />);

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() =>
      expect(
        screen.getByText('Aber alle sind wohlauf! Ihr seid an einem geheimnisvollen Strand gestrandet.'),
      ).toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() =>
      expect(screen.getByText('Hallo! Ich bin Pico. Ich zeige dir, was wir tun müssen!')).toBeInTheDocument(),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Los geht’s!' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Los geht’s!' }));

    expect(onFinished).toHaveBeenCalledTimes(1);
  });
});
