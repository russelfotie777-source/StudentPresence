"use client";

import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { Avatar, AvatarFallback, AvatarGroup } from "@/components/ui/avatar";
import {
  Progress,
  ProgressLabel,
  ProgressValue,
} from "@/components/ui/progress";
import { FUSEAU, useHeureDouala } from "@/hooks/use-heure";
import { usePresents } from "@/hooks/use-seances";
import type { Seance, UserRole } from "@/types/api";

/*
 * Ce qui fait qu'une séance est vivante : le temps qui passe et les gens
 * qui arrivent. Une ligne qui avance, le temps qu'il reste, et les prénoms
 * des camarades déjà là — rien de tout ça n'existe hors de cette heure.
 */

/** Minutes écoulées depuis minuit, heure de Douala, pour comparer à « 08:30 ». */
function minutesDouala(d: Date): number {
  const parts = new Intl.DateTimeFormat("fr-FR", {
    timeZone: FUSEAU,
    hour: "numeric",
    minute: "numeric",
    hour12: false,
  }).formatToParts(d);
  const h = Number(parts.find((p) => p.type === "hour")?.value ?? 0);
  const m = Number(parts.find((p) => p.type === "minute")?.value ?? 0);
  return h * 60 + m;
}

function minutes(heure: string): number {
  const [h, m] = heure.split(":").map(Number);
  return h * 60 + m;
}

function duree(min: number): string {
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60);
  const r = min % 60;
  return r ? `${h} h ${String(r).padStart(2, "0")}` : `${h} h`;
}

/** « Encore 42 min », « Dans 1 h 05 », « Terminée » — et la part du cours déjà passée. */
export function tempsDeSeance(
  seance: Seance,
  maintenant: Date | null,
): { texte: string; part: number } | null {
  if (!maintenant) return null;
  const now = minutesDouala(maintenant);
  const debut = minutes(seance.heure_debut);
  const fin = minutes(seance.heure_fin);
  if (now < debut) return { texte: `Dans ${duree(debut - now)}`, part: 0 };
  if (now >= fin) {
    // Le pointage reste ouvert un quart d'heure après la fin : on le dit
    // tant que c'est vrai, sinon la séance est simplement finie.
    const reste = fin + 15 - now;
    return {
      texte:
        seance.is_active && reste > 0
          ? `Terminée, pointage ouvert encore ${reste} min`
          : "Terminée",
      part: 1,
    };
  }
  return {
    texte: `Encore ${duree(fin - now)}`,
    part: (now - debut) / (fin - debut),
  };
}

/** Les horaires et, dessous, la part du cours déjà passée. */
export function LigneDeTemps({ seance }: { seance: Seance }) {
  const { maintenant } = useHeureDouala();
  const t = tempsDeSeance(seance, maintenant);

  return (
    <Progress
      value={t ? Math.round(t.part * 100) : 0}
      className="session-temps"
      aria-label="Avancement de la séance"
    >
      <ProgressLabel className="font-display text-base font-semibold">
        de {seance.heure_debut.slice(0, 5)} à {seance.heure_fin.slice(0, 5)}
      </ProgressLabel>
      {t && (
        <ProgressValue className="text-xs">{() => t.texte}</ProgressValue>
      )}
    </Progress>
  );
}

function liste(prenoms: string[]): string {
  if (prenoms.length <= 1) return prenoms.join("");
  return `${prenoms.slice(0, -1).join(", ")} et ${prenoms[prenoms.length - 1]}`;
}

/** La phrase, telle qu'on la dirait en entrant dans la salle. */
export function phrasePresents(
  d: { presents: number; effectif: number; moi: boolean; prenoms: string[] },
  role: UserRole,
): string {
  const responsable = role === "Delegue" || role === "Enseignant";

  if (d.presents === 0) {
    return responsable
      ? `Personne n'a encore pointé, sur ${d.effectif}.`
      : "Personne n'a encore pointé.";
  }

  // Les prénoms ne comptent jamais celui qui regarde : le reste, c'est les autres.
  const autres = d.presents - (d.moi ? 1 : 0);
  const reste = autres - d.prenoms.length;
  const noms =
    reste > 0
      ? `${d.prenoms.join(", ")} et ${reste} autre${reste > 1 ? "s" : ""}`
      : liste(d.prenoms);

  if (responsable) {
    const compte = `${d.presents} présent${d.presents > 1 ? "s" : ""} sur ${d.effectif}.`;
    return d.prenoms.length ? `${compte} Derniers arrivés\u00a0: ${noms}.` : compte;
  }
  if (d.moi && autres === 0) return "Vous avez pointé en premier.";
  return d.moi ? `Avec vous\u00a0: ${noms}.` : `Déjà là\u00a0: ${noms}.`;
}

export function PresentsEnDirect({
  seance,
  role,
}: {
  seance: Seance;
  role: UserRole;
}) {
  const { data } = usePresents(seance.id, seance.is_active);
  const queryClient = useQueryClient();

  // Le compte change dès que je pointe : pas besoin d'attendre le prochain tour.
  useEffect(() => {
    queryClient.invalidateQueries({
      queryKey: ["seances", seance.id, "presents"],
    });
  }, [seance.ma_presence, seance.id, queryClient]);

  if (!seance.is_active || !data) return null;

  return (
    <div className="session-presents">
      {data.prenoms.length > 0 && (
        <AvatarGroup>
          {data.prenoms.map((prenom) => (
            <Avatar key={prenom} size="sm">
              <AvatarFallback className="bg-primary/15 text-xs font-semibold text-primary">
                {prenom.charAt(0)}
              </AvatarFallback>
            </Avatar>
          ))}
        </AvatarGroup>
      )}
      <p>{phrasePresents(data, role)}</p>
    </div>
  );
}
