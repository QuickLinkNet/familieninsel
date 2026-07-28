import type { ActivityEntry } from '../../types/activity';

export function ActivityFeed({ entries }: { entries: ActivityEntry[] }) {
  if (entries.length === 0) {
    return null;
  }

  return (
    <section className="activity-feed">
      <h2>Familientagebuch</h2>
      <ul>
        {entries.map((entry) => (
          <li key={entry.id}>{entry.message}</li>
        ))}
      </ul>
    </section>
  );
}
