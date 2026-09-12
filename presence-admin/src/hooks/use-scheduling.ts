"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { Semaine } from "./use-catalog";

export function useGenerateSemester() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { date_debut: string; nombre_semaines: number }) =>
      apiFetch<Semaine[]>("/api/semaines/generate-semester", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["semaines"] }),
  });
}

export interface Enseignant {
  id: number;
  name: string;
}

export function useEnseignants() {
  return useQuery({
    queryKey: ["enseignants"],
    queryFn: () => apiFetch<Enseignant[]>("/api/enseignants"),
  });
}
