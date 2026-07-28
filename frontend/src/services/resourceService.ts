import { api } from './api';
import type { Resource } from '../types/resource';

export async function fetchResources(): Promise<Resource[]> {
  const { resources } = await api.get<{ resources: Resource[] }>('/resources');
  return resources;
}
