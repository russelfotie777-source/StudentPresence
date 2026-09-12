import type { Seance, Weekday } from "@/types/api";

export const JOURS: { valeur: Weekday; court: string; long: string }[] = [
  { valeur: "LUNDI", court: "Lun.", long: "Lundi" },
  { valeur: "MARDI", court: "Mar.", long: "Mardi" },
  { valeur: "MERCREDI", court: "Mer.", long: "Mercredi" },
  { valeur: "JEUDI", court: "Jeu.", long: "Jeudi" },
  { valeur: "VENDREDI", court: "Ven.", long: "Vendredi" },
  { valeur: "SAMEDI", court: "Sam.", long: "Samedi" },
  { valeur: "DIMANCHE", court: "Dim.", long: "Dimanche" },
];

/** Index ISO (lundi = 0 … dimanche = 6) d'un jour de semaine. */
export function indexJour(jour: Weekday): number {
  return JOURS.findIndex((j) => j.valeur === jour);
}

export function jourDepuisDate(date: Date): Weekday {
  // getDay() : 0 = dimanche … 6 = samedi.
  return JOURS[(date.getDay() + 6) % 7].valeur;
}

/**
 * Une couleur stable par matière : la même d'une semaine à l'autre et d'une
 * salle à l'autre, pour que l'œil reconnaisse un cours sans lire son nom.
 */
const PALETTE = [
  "bg-indigo-100 border-indigo-300/80 text-indigo-950 dark:bg-indigo-500/20 dark:border-indigo-400/40 dark:text-indigo-50",
  "bg-sky-100 border-sky-300/80 text-sky-950 dark:bg-sky-500/20 dark:border-sky-400/40 dark:text-sky-50",
  "bg-emerald-100 border-emerald-300/80 text-emerald-950 dark:bg-emerald-500/20 dark:border-emerald-400/40 dark:text-emerald-50",
  "bg-amber-100 border-amber-300/80 text-amber-950 dark:bg-amber-500/20 dark:border-amber-400/40 dark:text-amber-50",
  "bg-rose-100 border-rose-300/80 text-rose-950 dark:bg-rose-500/20 dark:border-rose-400/40 dark:text-rose-50",
  "bg-violet-100 border-violet-300/80 text-violet-950 dark:bg-violet-500/20 dark:border-violet-400/40 dark:text-violet-50",
  "bg-teal-100 border-teal-300/80 text-teal-950 dark:bg-teal-500/20 dark:border-teal-400/40 dark:text-teal-50",
  "bg-orange-100 border-orange-300/80 text-orange-950 dark:bg-orange-500/20 dark:border-orange-400/40 dark:text-orange-50",
];

const PASTILLES = [
  "bg-indigo-500",
  "bg-sky-500",
  "bg-emerald-500",
  "bg-amber-500",
  "bg-rose-500",
  "bg-violet-500",
  "bg-teal-500",
  "bg-orange-500",
];

function indiceCouleur(seance: Pick<Seance, "matiere_id" | "matiere">): number {
  const cle = seance.matiere_id ?? seance.matiere ?? 0;
  if (typeof cle === "number") return cle % PALETTE.length;
  let h = 0;
  for (const c of cle) h = (h * 31 + c.charCodeAt(0)) >>> 0;
  return h % PALETTE.length;
}

export function couleurCours(seance: Pick<Seance, "matiere_id" | "matiere">): string {
  return PALETTE[indiceCouleur(seance)];
}

export function pastilleCours(seance: Pick<Seance, "matiere_id" | "matiere">): string {
  return PASTILLES[indiceCouleur(seance)];
}

/**
 * Une séance « tenue » (état posé par le délégué ou l'enseignant, appel
 * verrouillé, présences enregistrées) est figée : l'API refuse de la
 * modifier ou de l'annuler, l'interface ne le propose donc pas.
 */
export function seanceFigee(seance: Seance): boolean {
  return (
    seance.etat_delegue !== null ||
    seance.etat_prof !== null ||
    seance.presences_locked ||
    (seance.presences_count ?? 0) > 0
  );
}
