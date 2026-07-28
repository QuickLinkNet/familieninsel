const ICONS: Record<string, string> = {
  wood: '🪵',
  metal: '⚙️',
  fabric: '🧵',
  rope: '🪢',
  stars: '⭐',
};

export function resourceIcon(key: string): string {
  return ICONS[key] ?? '❔';
}
