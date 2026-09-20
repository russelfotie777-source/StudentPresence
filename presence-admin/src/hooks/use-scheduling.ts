"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { Semaine } from "./use-catalog";

/** Ce que la prolongation a fait : cours prolongés, séances créées, et les créneaux qu'un cours n'a pas pu prendre. */
export interface Prolongation {
  cours: number;
  seances: number;
  ignorees: { cours_id: number; cours: string | null; date: string; reason: string }[];
}

const CLES_PLANNING = ["semaines", "emploi-du-temps", "course-templates", "historique-seances", "dashboard"];

export function useGenerateSemester() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { date_debut: string; nombre_semaines: number; prolonger_cours: boolean }) =>
      apiFetch<{ semaines: Semaine[]; prolongation: Prolongation | null }>("/api/semaines/generate-semester", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: () => CLES_PLANNING.forEach((cle) => queryClient.invalidateQueries({ queryKey: [cle] })),
  });
}

/** Prolonge les cours en cours jusqu'à la dernière semaine du calendrier — rejouable sans risque. */
export function useProlongerCours() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => apiFetch<Prolongation>("/api/course-templates/prolonger", { method: "POST", body: "{}" }),
    onSuccess: () => CLES_PLANNING.forEach((cle) => queryClient.invalidateQueries({ queryKey: [cle] })),
  });
}

/** La phrase qui résume une prolongation, pour le toast. */
export function resumerProlongation(p: Prolongation): string {
  if (p.cours === 0) return "Aucun cours à prolonger : l'emploi du temps couvre déjà toutes les semaines.";
  const base = `${p.cours} cours prolongé${p.cours > 1 ? "s" : ""}, ${p.seances} séance${p.seances > 1 ? "s" : ""} créée${p.seances > 1 ? "s" : ""}`;
  return p.ignorees.length ? `${base} — ${p.ignorees.length} créneau${p.ignorees.length > 1 ? "x" : ""} en conflit, laissé${p.ignorees.length > 1 ? "s" : ""} vide.` : `${base}.`;
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
