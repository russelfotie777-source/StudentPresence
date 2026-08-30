"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { RequestStatus, RequeteEnseignant } from "@/types/api";

export function useAdminRequetes(statut?: RequestStatus) {
  return useQuery({
    queryKey: ["requetes", statut ?? "all"],
    queryFn: () =>
      apiFetch<RequeteEnseignant[]>(`/api/requetes${statut ? `?statut=${statut}` : ""}`),
  });
}

export function useProcessRequete() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      action,
      commentaire,
    }: {
      id: number;
      action: "acceptee" | "rejetee";
      commentaire?: string;
    }) =>
      apiFetch(`/api/requetes/${id}/process`, {
        method: "POST",
        body: JSON.stringify({ action, commentaire }),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["requetes"] }),
  });
}
