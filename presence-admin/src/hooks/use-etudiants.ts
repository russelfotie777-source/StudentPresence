"use client";

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError, telechargerFichier } from "@/lib/api-client";
import type { PresenceState, Seance, StatutCompte, User } from "@/types/api";

interface PageEtudiants {
  data: User[];
  meta: { current_page: number; last_page: number; total: number };
}

export interface FiltresEtudiants {
  search: string;
  salleId?: number;
  statut?: StatutCompte;
}

export function useEtudiants(filtres: FiltresEtudiants) {
  return useInfiniteQuery({
    queryKey: ["etudiants", filtres],
    queryFn: ({ pageParam }) => {
      const params = new URLSearchParams({ page: String(pageParam) });
      if (filtres.search) params.set("search", filtres.search);
      if (filtres.salleId) params.set("salle_id", String(filtres.salleId));
      if (filtres.statut) params.set("statut", filtres.statut);
      return apiFetch<PageEtudiants>(`/api/etudiants?${params}`);
    },
    initialPageParam: 1,
    getNextPageParam: (d) =>
      d.meta.current_page < d.meta.last_page ? d.meta.current_page + 1 : undefined,
  });
}

function message(e: unknown, repli: string) {
  if (e instanceof ApiError) {
    const premiere = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
    return premiere ?? e.message;
  }
  return repli;
}

function useMutationEtudiant<TVariables>(
  executer: (v: TVariables) => Promise<unknown>,
  succes: string,
  repli: string,
) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: executer,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["etudiants"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      toast.success(succes);
    },
    onError: (e) => toast.error(message(e, repli)),
  });
}

export function useChangerSalle() {
  return useMutationEtudiant(
    (v: { id: number; salle_id: number }) =>
      apiFetch(`/api/etudiants/${v.id}/salle`, { method: "PUT", body: JSON.stringify({ salle_id: v.salle_id }) }),
    "Étudiant rattaché à sa nouvelle salle.",
    "Le changement de salle a échoué.",
  );
}

export function useChangerStatut() {
  return useMutationEtudiant(
    (v: { id: number; action: "restreindre" | "bloquer" | "retablir"; motif?: string }) =>
      apiFetch(`/api/etudiants/${v.id}/${v.action}`, {
        method: "POST",
        body: JSON.stringify({ motif: v.motif }),
      }),
    "Statut du compte mis à jour, l'étudiant en est notifié.",
    "Le changement de statut a échoué.",
  );
}

export function useSupprimerEtudiant() {
  return useMutationEtudiant(
    (id: number) => apiFetch(`/api/etudiants/${id}`, { method: "DELETE" }),
    "Compte supprimé.",
    "La suppression a échoué.",
  );
}

export function useForcerPresence() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (v: { seanceId: number; etudiantId: number; etat: PresenceState }) =>
      apiFetch(`/api/seances/${v.seanceId}/presences/${v.etudiantId}`, {
        method: "POST",
        body: JSON.stringify({ etat: v.etat }),
      }),
    onSuccess: (_d, v) => {
      queryClient.invalidateQueries({ queryKey: ["historique-seances"] });
      toast.success(v.etat === "present" ? "Présence enregistrée." : "Absence enregistrée.");
    },
    onError: (e) => toast.error(message(e, "L'enregistrement a échoué.")),
  });
}

/** Séances récentes d'une salle, pour choisir celle sur laquelle forcer une présence. */
export function useSeancesDeSalle(salleId: number | undefined) {
  return useQuery({
    queryKey: ["historique-seances", "salle", salleId],
    queryFn: () =>
      apiFetch<{ data: Seance[] }>(`/api/historique-seances?salle_id=${salleId}&per_page=12`),
    enabled: salleId !== undefined,
    select: (r) => r.data,
  });
}

export function useTelechargerListe() {
  return useMutation({
    mutationFn: (v: { salleId: number; semaineId: number; semestre?: number; annee?: string }) => {
      const params = new URLSearchParams({ semaine_id: String(v.semaineId) });
      if (v.semestre) params.set("semestre", String(v.semestre));
      if (v.annee) params.set("annee", v.annee);
      return telechargerFichier(`/api/salles/${v.salleId}/liste-presence.pdf?${params}`, "liste-presence.pdf");
    },
    onSuccess: () => toast.success("Liste de présence téléchargée."),
    onError: (e) => toast.error(message(e, "La génération a échoué.")),
  });
}
