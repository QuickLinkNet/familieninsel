import type { Resource } from '../../types/resource';
import { ResourceIcon } from './ResourceIcon';

export function ResourceBar({ resources }: { resources: Resource[] }) {
  if (resources.length === 0) {
    return null;
  }

  return (
    <div className="resource-bar">
      {resources.map((resource) => (
        <span key={resource.id} className="resource-pill">
          <ResourceIcon resourceKey={resource.key} className="resource-pill__icon" />
          <strong>{resource.amount}</strong> {resource.name}
        </span>
      ))}
    </div>
  );
}
