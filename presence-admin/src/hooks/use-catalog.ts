"use client";

import { makeCrudHooks } from "./use-crud";

export interface Niveau {
  id: number;
  nom: string;
}

export interface Filiere {
  id: number;
  nom: string;
  niveau_id: number;
  niveau?: Niveau;
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
  date_debut: string;
  date_fin: string;
}

export const niveauHooks = makeCrudHooks<Niveau>("niveaux", "niveaux");
export const filiereHooks = makeCrudHooks<Filiere>("filieres", "filieres");
export const salleHooks = makeCrudHooks<Salle>("salles", "salles");
export const matiereHooks = makeCrudHooks<Matiere>("matieres", "matieres");
export const semaineHooks = makeCrudHooks<Semaine>("semaines", "semaines");
