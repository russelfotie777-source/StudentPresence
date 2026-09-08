"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

export interface DashboardStats {
  a_traiter: {
    delegues: number;
    enseignants: number;
    requetes: number;
    migrations: number;
  };
  activite: {
    seances_aujourdhui: number;
    jours_observes: number;
    seances_recentes: number;
    seances_presentes: number;
    /** `null` quand aucune séance n'a eu lieu : rien à mesurer, ce qui n'est pas 0 %. */
    taux_presence: number | null;
  };
  catalogue: {
    niveaux: number;
    filieres: number;
    salles: number;
    matieres: number;
    enseignants: number;
    etudiants: number;
  };
}

export function useDashboard() {
  return useQuery({
    queryKey: ["dashboard"],
    queryFn: () => apiFetch<DashboardStats>("/api/dashboard"),
    // Un back-office reste ouvert des heures : ces chiffres doivent refléter
    // ce que font délégués et enseignants pendant ce temps.
    refetchInterval: 60_000,
  });
}
