"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError } from "@/lib/api-client";
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
    onSuccess: (_data, { action }) => {
      queryClient.invalidateQueries({ queryKey: ["requetes"] });
      // La vue d'ensemble compte les requêtes en attente : sans ça elle
      // continuerait d'en annoncer une déjà traitée.
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });

      toast.success(
        action === "acceptee"
          ? "Requête acceptée — la séance est marquée présente et devient payable."
          : "Requête rejetée.",
      );
    },
    onError: (error) =>
      toast.error(error instanceof ApiError ? error.message : "Le traitement a échoué."),
  });
}
