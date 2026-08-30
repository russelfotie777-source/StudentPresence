"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import { makeCrudHooks } from "./use-crud";
import type { Weekday } from "@/types/api";

export interface CourseTemplate {
  id: number;
  matiere_id: number;
  enseignant_id: number;
  salle_id: number;
  groupe: string;
  jour: Weekday;
  heure_debut: string;
  heure_fin: string;
  date_debut: string;
  date_fin: string;
  actif: boolean;
  matiere?: { nom: string };
  enseignant?: { name: string };
  salle?: { nom: string };
}

export const courseTemplateHooks = makeCrudHooks<CourseTemplate>("course-templates", "course-templates");

export function useGenerateSemester() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { date_debut: string; nombre_semaines: number }) =>
      apiFetch("/api/semaines/generate-semester", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["semaines"] }),
  });
}

export interface GenerationResult {
  created: unknown[];
  skipped: { semaine_id: number; reason: string }[];
}

export function useGenerateSeances() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (templateId: number) =>
      apiFetch<GenerationResult>(`/api/course-templates/${templateId}/generate`, {
        method: "POST",
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["historique-seances"] }),
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
