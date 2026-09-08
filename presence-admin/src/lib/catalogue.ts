import type { Salle } from "@/hooks/use-catalog";

/**
 * Nom d'une salle avec sa filière et son niveau — une salle physique
 * partagée existe en base comme deux entrées distinctes (FI le matin, FA le
 * soir), et leurs noms se répètent d'une filière à l'autre : le nom seul ne
 * suffit pas à choisir la bonne.
 */
export function libelleSalle(s: Salle): string {
  const contexte = [s.filiere?.nom, s.filiere?.niveau?.nom].filter(Boolean).join(" · ");

  return contexte ? `${s.nom} — ${contexte}` : s.nom;
}
