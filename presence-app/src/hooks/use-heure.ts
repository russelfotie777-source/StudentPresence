"use client";

import { useSyncExternalStore } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

/** Fuseau de référence de l'application — celui du campus, pas du téléphone. */
export const FUSEAU = "Africa/Douala";

interface ReponseHeure {
  maintenant: string;
  fuseau: string;
  date: string;
  heure: string;
  jour: string;
}

/**
 * Horloge partagée qui bat une fois par seconde, exposée comme un store
 * externe : lire Date.now() pendant le rendu rendrait le composant impur,
 * et un état par composant ferait autant de minuteries.
 */
let horloge = 0;
const abonnes = new Set<() => void>();
let minuterie: ReturnType<typeof setInterval> | null = null;

function abonner(rappel: () => void) {
  abonnes.add(rappel);
  if (abonnes.size === 1) {
    horloge = Date.now();
    minuterie = setInterval(() => {
      horloge = Date.now();
      abonnes.forEach((r) => r());
    }, 1000);
    queueMicrotask(rappel);
  }
  return () => {
    abonnes.delete(rappel);
    if (abonnes.size === 0 && minuterie) {
      clearInterval(minuterie);
      minuterie = null;
    }
  };
}

const lireHorloge = () => horloge;
const horlogeServeur = () => 0;

/**
 * L'heure qui fait foi : celle de Douala, servie par l'API. L'horloge du
 * téléphone ne sert qu'à faire avancer l'aiguille entre deux
 * synchronisations (on applique l'écart mesuré à la réception) — jamais à
 * décider quel jour on est : un téléphone mal réglé, ou un étudiant en
 * voyage, verrait sinon une date qui ne correspond pas aux séances listées.
 */
export function useHeureDouala() {
  const { data, dataUpdatedAt } = useQuery({
    queryKey: ["heure"],
    queryFn: () => apiFetch<ReponseHeure>("/api/heure"),
    refetchInterval: 5 * 60_000,
    refetchOnWindowFocus: true,
    staleTime: 60_000,
  });
  const tic = useSyncExternalStore(abonner, lireHorloge, horlogeServeur);

  if (!data || tic === 0) {
    return { pret: false as const, maintenant: null };
  }

  return {
    pret: true as const,
    maintenant: new Date(new Date(data.maintenant).getTime() + (tic - dataUpdatedAt)),
  };
}
