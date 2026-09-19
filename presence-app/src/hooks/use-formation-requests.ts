"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError } from "@/lib/api-client";
import type { DemandeFormation, SituationMigration } from "@/types/api";

export function useSituationMigration(enabled = true) {
  return useQuery({
    queryKey: ["migration", "situation"],
    queryFn: () => apiFetch<SituationMigration>("/api/me/migration"),
    enabled,
  });
}

export function useMyFormationRequests(enabled: boolean) {
  return useQuery({
    queryKey: ["formation-requests", "mine"],
    queryFn: () => apiFetch<DemandeFormation[]>("/api/me/formation-requests"),
    enabled,
  });
}

function invalider(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["formation-requests", "mine"] });
  queryClient.invalidateQueries({ queryKey: ["migration"] });
  // L'approbation change la salle de l'étudiant : le profil et l'accueil suivent.
  queryClient.invalidateQueries({ queryKey: ["me"] });
}

export function useSubmitFormationRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: { salle_cible_id: number; motif?: string }) =>
      apiFetch<DemandeFormation>("/api/formation-requests", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: () => {
      invalider(queryClient);
      toast.success("Demande envoyée à l'administration.");
    },
    onError: (error) => {
      toast.error(
        error instanceof ApiError
          ? (Object.values(error.errors ?? {}).flat()[0] ?? error.message)
          : "L'envoi a échoué.",
      );
    },
  });
}

export function useWithdrawFormationRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(`/api/formation-requests/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      invalider(queryClient);
      toast.success("Demande retirée.");
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Le retrait a échoué.");
    },
  });
}
