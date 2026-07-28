import { api } from './api';
import type { ActivityEntry } from '../types/activity';

export async function fetchActivity(): Promise<ActivityEntry[]> {
  const { entries } = await api.get<{ entries: ActivityEntry[] }>('/activity');
  return entries;
}
