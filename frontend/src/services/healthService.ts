import { api } from './api';

export interface HealthStatus {
  status: 'ok';
  database: 'connected';
  time: string;
}

export function fetchHealth(): Promise<HealthStatus> {
  return api.get<HealthStatus>('/health');
}
