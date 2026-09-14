import { resourceIconSrc } from '../../utils/resourceIcons';

interface ResourceIconProps {
  resourceKey: string;
  className?: string;
}

export function ResourceIcon({ resourceKey, className }: ResourceIconProps) {
  const src = resourceIconSrc(resourceKey);
  const classes = ['resource-icon', className].filter(Boolean).join(' ');

  if (src === undefined) {
    return (
      <span className={classes} aria-hidden="true">
        ❔
      </span>
    );
  }

  return <img src={src} alt="" aria-hidden="true" className={classes} />;
}
