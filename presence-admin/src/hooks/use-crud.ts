"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError } from "@/lib/api-client";

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

  /**
   * Le catalogue alimente presque tous les autres écrans : une salle créée
   * doit apparaître aussitôt dans les migrations, l'historique ou la vue
   * d'ensemble, sans recharger la page.
   */
  function useInvalider() {
    const queryClient = useQueryClient();

    return () => {
      queryClient.invalidateQueries({ queryKey: [queryKey] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    };
  }

  function message(error: unknown, repli: string) {
    return error instanceof ApiError ? error.message : repli;
  }

  function useCreate() {
    const invalider = useInvalider();

    return useMutation({
      mutationFn: (data: Record<string, unknown>) =>
        apiFetch<T>(`/api/${endpoint}`, { method: "POST", body: JSON.stringify(data) }),
      onSuccess: () => {
        invalider();
        toast.success("Créé.");
      },
      onError: (error) => toast.error(message(error, "La création a échoué.")),
    });
  }

  function useUpdate() {
    const invalider = useInvalider();

    return useMutation({
      mutationFn: ({ id, data }: { id: number; data: Record<string, unknown> }) =>
        apiFetch<T>(`/api/${endpoint}/${id}`, { method: "PUT", body: JSON.stringify(data) }),
      onSuccess: () => {
        invalider();
        toast.success("Modifié.");
      },
      onError: (error) => toast.error(message(error, "La modification a échoué.")),
    });
  }

  function useRemove() {
    const invalider = useInvalider();

    return useMutation({
      mutationFn: (id: number) => apiFetch(`/api/${endpoint}/${id}`, { method: "DELETE" }),
      onSuccess: () => {
        invalider();
        toast.success("Supprimé.");
      },
      // Une suppression peut être refusée par la base — une salle rattachée à
      // des séances, par exemple. Sans ce retour, le clic restait sans effet
      // visible et sans explication.
      onError: (error) =>
        toast.error(
          message(error, "Suppression impossible : cet élément est encore utilisé ailleurs."),
        ),
    });
  }

  return { useList, useCreate, useUpdate, useRemove };
}
