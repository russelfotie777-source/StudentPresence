"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { Semaine } from "./use-catalog";
import type { Seance, Weekday } from "@/types/api";

export type ModeGrille = "salle" | "enseignant";

interface ReponseGrille {
  data: Seance[];
  semaine: Semaine | null;
  maintenant: string;
}

export function useEmploiDuTemps(mode: ModeGrille, id: number | null, semaineId: number | null) {
  const params = new URLSearchParams();
  if (id) params.set(mode === "salle" ? "salle_id" : "enseignant_id", String(id));
  if (semaineId) params.set("semaine_id", String(semaineId));

  return useQuery({
    queryKey: ["emploi-du-temps", mode, id, semaineId],
    queryFn: () => apiFetch<ReponseGrille>(`/api/emploi-du-temps?${params}`),
    enabled: id !== null,
    refetchInterval: 60_000,
  });
}

/**
 * Tout ce qui touche aux séances se répercute sur la grille, l'historique,
 * la vue d'ensemble et les cours récurrents : une seule liste à tenir.
 */
function useInvaliderSeances() {
  const queryClient = useQueryClient();
  return () => {
    for (const cle of ["emploi-du-temps", "course-templates", "historique-seances", "dashboard", "semaines"]) {
      queryClient.invalidateQueries({ queryKey: [cle] });
    }
  };
}

export interface ProgrammationCours {
  matiere_id: number;
  enseignant_id: number;
  salle_id: number;
  jour: Weekday;
  heure_debut: string;
  heure_fin: string;
  date_debut: string;
  date_fin: string;
}

export interface SemaineIgnoree {
  semaine_id: number;
  numero: number;
  date: string;
  reason: string;
}

export interface ResultatProgrammation {
  created: Seance[];
  skipped: SemaineIgnoree[];
}

export function useProgrammerCours() {
  const invalider = useInvaliderSeances();
  return useMutation({
    mutationFn: (cours: ProgrammationCours) =>
      apiFetch<ResultatProgrammation>("/api/course-templates", {
        method: "POST",
        body: JSON.stringify({ ...cours, generer: true }),
      }),
    onSuccess: invalider,
  });
}

export interface ModificationSeance {
  date_seance?: string;
  heure_debut?: string;
  heure_fin?: string;
  enseignant_id?: number;
}

export function useModifierSeance() {
  const invalider = useInvaliderSeances();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: ModificationSeance }) =>
      apiFetch<Seance>(`/api/seances/${id}`, { method: "PUT", body: JSON.stringify(data) }),
    onSuccess: invalider,
  });
}

export function useAnnulerSeance() {
  const invalider = useInvaliderSeances();
  return useMutation({
    mutationFn: (id: number) => apiFetch(`/api/seances/${id}`, { method: "DELETE" }),
    onSuccess: invalider,
  });
}

export function useSupprimerCours() {
  const invalider = useInvaliderSeances();
  return useMutation({
    mutationFn: (templateId: number) =>
      apiFetch<{ seances_supprimees: number }>(`/api/course-templates/${templateId}`, {
        method: "DELETE",
      }),
    onSuccess: invalider,
  });
}
