"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError } from "@/lib/api-client";
import type { Semaine } from "./use-catalog";
import type { FormationType, PresenceState, StatutCompte, UserRole, Weekday } from "@/types/api";

export type Symboles = "coche" | "valeur";

/** Situation d'une séance vis-à-vis de l'appel — décidée par l'API (FeuilleDePresence). */
export type StatutSeance = "tenue" | "en_cours" | "a_venir" | "non_validee";

export interface SeanceFeuille {
  id: number;
  date_seance: string;
  jour: Weekday;
  heure_debut: string;
  heure_fin: string;
  matiere: string | null;
  enseignant: string | null;
  statut: StatutSeance;
  presences_locked: boolean;
  presences_count: number;
}

export interface EtudiantFeuille {
  id: number;
  name: string;
  phone: string;
  role: UserRole;
  formation: FormationType | null;
  statut_compte: StatutCompte;
  motif_statut: string | null;
  presence_automatique?: boolean;
  presence_automatique_motif?: string | null;
  /** seance_id → état retenu, null quand il n'y a rien à dire. */
  presences: Record<string, PresenceState | null>;
}

export interface SalleFeuille {
  id: number;
  nom: string;
  formation: "FI" | "FA";
  filiere: string | null;
  niveau: string | null;
  departement: { id: number; nom: string; code: string } | null;
}

export interface FeuillePresence {
  salle: SalleFeuille;
  semaine: Semaine | null;
  symboles: Symboles;
  maintenant?: string;
  delegue: { id: number; name: string } | null;
  seances: SeanceFeuille[];
  etudiants: EtudiantFeuille[];
}

export function useFeuillePresence(salleId: number | null, semaineId: number | null) {
  const params = new URLSearchParams();
  if (semaineId) params.set("semaine_id", String(semaineId));

  return useQuery({
    queryKey: ["feuille-presence", salleId, semaineId],
    queryFn: () => apiFetch<FeuillePresence>(`/api/salles/${salleId}/feuille-presence?${params}`),
    enabled: salleId !== null,
    refetchInterval: 60_000,
  });
}

/** Le symbole affiché pour un état, dans la notation choisie par l'admin. */
export function symbole(symboles: Symboles, etat: PresenceState): string {
  if (symboles === "valeur") return etat === "present" ? "+1" : "−1";
  return etat === "present" ? "✓" : "✗";
}

interface ReglageSymboles {
  symboles: Symboles;
  choix: Symboles[];
}

export function useSymboles() {
  return useQuery({
    queryKey: ["parametres", "liste-presence"],
    queryFn: () => apiFetch<ReglageSymboles>("/api/parametres/liste-presence"),
    staleTime: 5 * 60_000,
  });
}

export function useChangerSymboles() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (symboles: Symboles) =>
      apiFetch<ReglageSymboles>("/api/parametres/liste-presence", {
        method: "PUT",
        body: JSON.stringify({ symboles }),
      }),
    onSuccess: (r) => {
      queryClient.setQueryData(["parametres", "liste-presence"], r);
      queryClient.invalidateQueries({ queryKey: ["feuille-presence"] });
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Le réglage n'a pas été enregistré."),
  });
}
