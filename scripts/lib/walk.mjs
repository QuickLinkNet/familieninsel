import { readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

export function walkFiles(directory, extension) {
  const results = [];

  for (const entry of readdirSync(directory)) {
    const fullPath = join(directory, entry);
    const stats = statSync(fullPath);

    if (stats.isDirectory()) {
      results.push(...walkFiles(fullPath, extension));
    } else if (entry.endsWith(extension)) {
      results.push(fullPath);
    }
  }

  return results;
}
