"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { DemandeFormation, RequestStatus } from "@/types/api";

export function useFormationRequests(statut?: RequestStatus) {
  return useQuery({
    queryKey: ["formation-requests", statut ?? "all"],
    queryFn: () =>
      apiFetch<DemandeFormation[]>(`/api/formation-requests${statut ? `?statut=${statut}` : ""}`),
  });
}

export function useApproveFormationRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, salle_id }: { id: number; salle_id: number }) =>
      apiFetch<DemandeFormation>(`/api/formation-requests/${id}/approve`, {
        method: "POST",
        body: JSON.stringify({ salle_id }),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["formation-requests"] }),
  });
}

export function useRejectFormationRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, commentaire }: { id: number; commentaire?: string }) =>
      apiFetch<DemandeFormation>(`/api/formation-requests/${id}/reject`, {
        method: "POST",
        body: JSON.stringify({ commentaire }),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["formation-requests"] }),
  });
}
