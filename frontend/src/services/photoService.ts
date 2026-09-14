import { api } from './api';

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? '/api';

export function photoUrl(playerId: number): string {
  return `${API_BASE_URL}/players/${playerId}/photo`;
}

export async function uploadPlayerPhoto(playerId: number, file: File): Promise<void> {
  const formData = new FormData();
  formData.append('photo', file);
  await api.postForm(`/players/${playerId}/photo`, formData);
}
