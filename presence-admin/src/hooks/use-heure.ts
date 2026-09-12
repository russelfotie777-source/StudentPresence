"use client";

import { useSyncExternalStore } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import { instantADouala, type Ymd } from "@/lib/dates";

interface ReponseHeure {
  maintenant: string;
  fuseau: string;
  date: Ymd;
  heure: string;
  jour: string;
}

/**
 * Horloge locale partagée qui bat une fois par seconde, exposée comme un
 * store externe : lire Date.now() pendant le rendu est interdit (rendu
 * impur), et un état React par composant ferait autant de minuteries.
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
 * Heure de référence de l'application : celle de Douala, servie par l'API.
 * L'horloge de la machine ne sert qu'à faire avancer l'aiguille entre deux
 * synchronisations (on applique l'écart mesuré à la réception), jamais à
 * décider quel jour ou quelle heure il est.
 */
export function useHeureDouala() {
  const { data, dataUpdatedAt } = useQuery({
    queryKey: ["heure"],
    queryFn: () => apiFetch<ReponseHeure>("/api/heure"),
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
    staleTime: 30_000,
  });
  const tic = useSyncExternalStore(abonner, lireHorloge, horlogeServeur);

  if (!data || tic === 0) {
    return { pret: false as const, maintenant: null, date: null, minute: null, fuseau: null };
  }

  const maintenant = new Date(new Date(data.maintenant).getTime() + (tic - dataUpdatedAt));
  const { date, minute } = instantADouala(maintenant);

  return { pret: true as const, maintenant, date, minute, fuseau: data.fuseau };
}
