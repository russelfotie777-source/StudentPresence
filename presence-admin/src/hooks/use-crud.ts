"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

/**
 * Fabrique de hooks CRUD génériques pour les entités du catalogue admin
 * (niveaux, filières, salles, matières, semaines...) — toutes suivent le
 * même schéma REST côté API (apiResource Laravel), pas la peine de
 * dupliquer les mêmes 4 hooks pour chacune.
 */
export function makeCrudHooks<T extends { id: number }>(endpoint: string, queryKey: string) {
  function useList(query = "") {
    return useQuery({
      queryKey: [queryKey, query],
      queryFn: () => apiFetch<T[]>(`/api/${endpoint}${query}`),
    });
  }

  function useCreate() {
    const queryClient = useQueryClient();
    return useMutation({
      mutationFn: (data: Record<string, unknown>) =>
        apiFetch<T>(`/api/${endpoint}`, { method: "POST", body: JSON.stringify(data) }),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: [queryKey] }),
    });
  }

  function useUpdate() {
    const queryClient = useQueryClient();
    return useMutation({
      mutationFn: ({ id, data }: { id: number; data: Record<string, unknown> }) =>
        apiFetch<T>(`/api/${endpoint}/${id}`, { method: "PUT", body: JSON.stringify(data) }),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: [queryKey] }),
    });
  }

  function useRemove() {
    const queryClient = useQueryClient();
    return useMutation({
      mutationFn: (id: number) => apiFetch(`/api/${endpoint}/${id}`, { method: "DELETE" }),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: [queryKey] }),
    });
  }

  return { useList, useCreate, useUpdate, useRemove };
}
