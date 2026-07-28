import { api } from './api';
import type { Task } from '../types/task';

export async function fetchTasks(): Promise<Task[]> {
  const { tasks } = await api.get<{ tasks: Task[] }>('/tasks');
  return tasks;
}

export interface CreateTaskInput {
  assignedPlayerId: number;
  title: string;
  description?: string;
  rewards: Record<string, number>;
}

export async function createTask(input: CreateTaskInput): Promise<number> {
  const { taskId } = await api.post<{ taskId: number }>('/tasks', input);
  return taskId;
}

export async function completeTask(taskId: number): Promise<void> {
  await api.post(`/tasks/${taskId}/complete`);
}

export async function approveTask(taskId: number): Promise<void> {
  await api.post(`/tasks/${taskId}/approve`);
}

export async function rejectTask(taskId: number, parentNote?: string): Promise<void> {
  await api.post(`/tasks/${taskId}/reject`, { parentNote });
}
