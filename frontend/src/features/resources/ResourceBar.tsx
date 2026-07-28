import type { Resource } from '../../types/resource';
import { resourceIcon } from '../../utils/resourceIcons';

export function ResourceBar({ resources }: { resources: Resource[] }) {
  if (resources.length === 0) {
    return null;
  }

  return (
    <div className="resource-bar">
      {resources.map((resource) => (
        <span key={resource.id} className="resource-pill">
          <span aria-hidden="true">{resourceIcon(resource.key)}</span>
          <strong>{resource.amount}</strong> {resource.name}
        </span>
      ))}
    </div>
  );
}
