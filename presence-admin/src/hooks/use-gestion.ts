"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError, telechargerFichier } from "@/lib/api-client";
import type { FormatExport } from "@/hooks/use-etudiants";

export interface MigrantEtudiant {
  id: number;
  name: string;
  phone: string;
  role: "Etudiant" | "Delegue";
  statut_compte: "actif" | "restreint" | "bloque";
  presence_automatique: boolean;
  salle_origine: string | null;
  migre_le: string | null;
  appels: number;
  presents: number;
  taux: number | null;
}

export interface Migrants {
  total: number;
  demandes_en_attente: number;
  salles: {
    salle: {
      id: number;
      nom: string;
      formation: "FI" | "FA";
      filiere: string | null;
      niveau: string | null;
      departement: string | null;
    } | null;
    etudiants: MigrantEtudiant[];
  }[];
}

export interface Privilegie {
  id: number;
  name: string;
  phone: string;
  formation: "FI" | "FA" | "FM" | null;
  salle: { id: number; nom: string } | null;
  presence_automatique: boolean;
  motif: string | null;
  depuis: string | null;
}

export interface Comptabilite {
  periode: { du: string; au: string };
  effectifs: {
    etudiants: number;
    par_formation: { FI: number; FA: number; FM: number };
    delegues: number;
    enseignants: number;
    comptes_restreints: number;
    presence_automatique: number;
    par_departement: { code: string; nom: string; etudiants: number; salles: number }[];
  };
  seances: {
    programmees: number;
    passees: number;
    tenues: number;
    non_tenues: number;
    taux_tenue: number | null;
    confirmees_par_delegue: number;
    heures_effectuees: number;
    a_venir: number;
  };
  assiduite: {
    appels: number;
    presents: number;
    absents: number;
    taux: number | null;
    forcees_par_admin: number;
    automatiques: number;
  };
  migrations: { en_attente: number; acceptees: number; rejetees: number };
  paie: {
    enseignants: { id: number; name: string; seances: number; heures: number; salaire: number; penalites: number }[];
    total_salaire: number;
    total_penalites: number;
    total_heures: number;
  };
}

export function useMigrants() {
  return useQuery({ queryKey: ["migrants"], queryFn: () => apiFetch<Migrants>("/api/migrants") });
}

export function usePrivilegies() {
  return useQuery({
    queryKey: ["presence-automatique"],
    queryFn: () => apiFetch<Privilegie[]>("/api/etudiants/presence-automatique"),
  });
}

export function useComptabilite(du?: string, au?: string) {
  const params = new URLSearchParams();
  if (du) params.set("du", du);
  if (au) params.set("au", au);
  return useQuery({
    queryKey: ["comptabilite", du ?? "", au ?? ""],
    queryFn: () => apiFetch<Comptabilite>(`/api/comptabilite?${params}`),
  });
}

function message(error: unknown, repli: string) {
  return error instanceof ApiError
    ? (Object.values(error.errors ?? {}).flat()[0] ?? error.message)
    : repli;
}

/** Accorde ou retire le privilège « toujours présent ». */
export function usePresenceAutomatique() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, actif, motif }: { id: number; actif: boolean; motif?: string }) =>
      apiFetch<Privilegie>(`/api/etudiants/${id}/presence-automatique`, {
        method: "POST",
        body: JSON.stringify({ actif, motif }),
      }),
    onSuccess: (r) => {
      for (const cle of ["presence-automatique", "migrants", "feuille-presence", "etudiants", "comptabilite"]) {
        queryClient.invalidateQueries({ queryKey: [cle] });
      }
      toast.success(
        r.presence_automatique
          ? `${r.name} sera compté présent à chaque séance.`
          : `${r.name} pointe à nouveau comme les autres.`,
      );
    },
    onError: (e) => toast.error(message(e, "Le changement a échoué.")),
  });
}

/** La liste de présence des seuls migrants d'une salle d'accueil, pour une semaine. */
export function useTelechargerListeMigrants() {
  return useMutation({
    mutationFn: ({ salleId, semaineId, format = "pdf" }: { salleId: number; semaineId: number; format?: FormatExport }) =>
      telechargerFichier(
        `/api/salles/${salleId}/liste-presence.${format}?semaine_id=${semaineId}&migrants=1`,
        `liste-presence-migrants.${format}`,
      ),
    onSuccess: () => toast.success("Liste des migrants téléchargée."),
    onError: (e) => toast.error(message(e, "La génération a échoué.")),
  });
}

/** La comptabilité de la période affichée, en classeur : une feuille de synthèse, une feuille de paie. */
export function useTelechargerComptabilite() {
  return useMutation({
    mutationFn: ({ du, au }: { du?: string; au?: string }) => {
      const params = new URLSearchParams();
      if (du) params.set("du", du);
      if (au) params.set("au", au);
      return telechargerFichier(`/api/comptabilite.xlsx?${params}`, "comptabilite.xlsx");
    },
    onSuccess: () => toast.success("Comptabilité téléchargée."),
    onError: (e) => toast.error(message(e, "La génération a échoué.")),
  });
}
