import wood from '../assets/icons/wood.webp';
import metal from '../assets/icons/metal.webp';
import fabric from '../assets/icons/fabric.webp';
import rope from '../assets/icons/rope.webp';
import stars from '../assets/icons/stars.webp';

const ICONS: Record<string, string> = { wood, metal, fabric, rope, stars };

export function resourceIconSrc(key: string): string | undefined {
  return ICONS[key];
}
