"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { Notification } from "@/types/api";

interface ReponseNotifications {
  notifications: Notification[];
  non_lues: number;
}

/**
 * Notifications de la personne connectée — c'est ainsi qu'un étudiant
 * apprend qu'un admin a restreint ou rétabli son compte. Rafraîchies à
 * intervalle : l'app reste ouverte des heures pendant les cours.
 */
export function useNotifications(enabled = true) {
  return useQuery({
    queryKey: ["notifications"],
    queryFn: () => apiFetch<ReponseNotifications>("/api/me/notifications"),
    enabled,
    refetchInterval: 60_000,
  });
}

export function useMarquerLue() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => apiFetch(`/api/me/notifications/${id}/lue`, { method: "POST" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });
}
