"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { DemandeFormation } from "@/types/api";

export function useMyFormationRequests(enabled: boolean) {
  return useQuery({
    queryKey: ["formation-requests", "mine"],
    queryFn: () => apiFetch<DemandeFormation[]>("/api/me/formation-requests"),
    enabled,
  });
}

export function useSubmitFormationRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (motif?: string) =>
      apiFetch<DemandeFormation>("/api/formation-requests", {
        method: "POST",
        body: JSON.stringify({ motif }),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["formation-requests", "mine"] });
    },
  });
}
