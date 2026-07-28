import { useCallback, useEffect, useState } from 'react';
import type { Task } from '../types/task';
import type { Resource } from '../types/resource';
import { ApiError } from '../types/api';
import { fetchTasks } from '../services/taskService';
import { fetchResources } from '../services/resourceService';

export function useTasksAndResources() {
  const [tasks, setTasks] = useState<Task[]>([]);
  const [resources, setResources] = useState<Resource[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setError(null);
    try {
      const [nextTasks, nextResources] = await Promise.all([fetchTasks(), fetchResources()]);
      setTasks(nextTasks);
      setResources(nextResources);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Verbindung zum Server fehlgeschlagen.');
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    refresh()
      .catch(() => undefined)
      .finally(() => setLoading(false));
  }, [refresh]);

  return { tasks, resources, loading, error, refresh };
}
