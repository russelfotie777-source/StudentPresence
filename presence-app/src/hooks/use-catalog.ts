"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

export interface Niveau {
  id: number;
  nom: string;
}

export interface Filiere {
  id: number;
  nom: string;
  niveau_id: number;
}

export interface Salle {
  id: number;
  nom: string;
  filiere_id: number;
  formation: "FI" | "FA";
}

export function useNiveaux() {
  return useQuery({
    queryKey: ["niveaux"],
    queryFn: () => apiFetch<Niveau[]>("/api/niveaux"),
    staleTime: 5 * 60_000,
  });
}

export function useFilieres(niveauId?: number) {
  return useQuery({
    queryKey: ["filieres", niveauId],
    queryFn: () => apiFetch<Filiere[]>(`/api/filieres?niveau_id=${niveauId}`),
    enabled: !!niveauId,
    staleTime: 5 * 60_000,
  });
}

export function useSalles(filiereId?: number) {
  return useQuery({
    queryKey: ["salles", filiereId],
    queryFn: () => apiFetch<Salle[]>(`/api/salles?filiere_id=${filiereId}`),
    enabled: !!filiereId,
    staleTime: 5 * 60_000,
  });
}
