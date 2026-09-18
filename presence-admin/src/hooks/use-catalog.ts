"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import { makeCrudHooks } from "./use-crud";

export interface Departement {
  id: number;
  nom: string;
  /** Sigle : « GI », « GRT ». */
  code: string;
  /** Nom anglais pour l'en-tête bilingue des listes officielles. */
  nom_en: string | null;
  filieres_count?: number;
  salles_count?: number;
}

export interface Niveau {
  id: number;
  nom: string;
}

export interface Filiere {
  id: number;
  nom: string;
  niveau_id: number;
  departement_id: number;
  niveau?: Niveau;
  departement?: Departement;
}

export interface Salle {
  id: number;
  nom: string;
  filiere_id: number;
  formation: "FI" | "FA";
  filiere?: Filiere;
}

export interface Matiere {
  id: number;
  nom: string;
  code: string;
}

export interface Semaine {
  id: number;
  numero: number;
  /** Dates pures « AAAA-MM-JJ », à lire avec dateLocale() — jamais via new Date(iso). */
  date_debut: string;
  date_fin: string;
  seances_count?: number;
}

/** Un département déployé niveau par niveau — tous les niveaux y figurent, même vides. */
export interface ArborescenceDepartement extends Departement {
  filieres_count: number;
  salles_count: number;
  niveaux: {
    id: number;
    nom: string;
    filieres: {
      id: number;
      nom: string;
      salles: { id: number; nom: string; formation: "FI" | "FA" }[];
    }[];
  }[];
}

// La structure est un tout : une salle embarque sa filière, son niveau et son
// département, l'arbre d'un département embarque tout le reste. Toucher à
// l'un périme les listes des autres — comme le fait le cache de l'API.
const STRUCTURE = ["departements", "niveaux", "filieres", "salles"];

export const departementHooks = makeCrudHooks<Departement>("departements", "departements", STRUCTURE);
export const niveauHooks = makeCrudHooks<Niveau>("niveaux", "niveaux", STRUCTURE);
export const filiereHooks = makeCrudHooks<Filiere>("filieres", "filieres", STRUCTURE);
export const salleHooks = makeCrudHooks<Salle>("salles", "salles", STRUCTURE);
export const matiereHooks = makeCrudHooks<Matiere>("matieres", "matieres");
export const semaineHooks = makeCrudHooks<Semaine>("semaines", "semaines");

export function useArborescenceDepartement(id: number | null) {
  return useQuery({
    // Sous la clé « departements » : une filière ou une salle créée ailleurs
    // invalide la liste, et l'arbre avec elle.
    queryKey: ["departements", "arborescence", id],
    queryFn: () => apiFetch<ArborescenceDepartement>(`/api/departements/${id}`),
    enabled: id !== null,
  });
}
