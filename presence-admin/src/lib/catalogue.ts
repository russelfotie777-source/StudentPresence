import type { Salle } from "@/hooks/use-catalog";

/**
 * Nom d'une salle avec son département, sa filière et son niveau — une
 * salle physique partagée existe en base comme deux entrées distinctes (FI
 * le matin, FA le soir), et leurs noms se répètent d'un département à
 * l'autre : le nom seul ne suffit pas à choisir la bonne. La filière tronc
 * commun (du nom du département) n'est pas répétée après le sigle.
 */
export function libelleSalle(s: Salle): string {
  const departement = s.filiere?.departement;
  const filiere = s.filiere?.nom && s.filiere.nom !== departement?.nom ? s.filiere.nom : undefined;
  const contexte = [departement?.code, filiere, s.filiere?.niveau?.nom].filter(Boolean).join(" · ");

  return contexte ? `${s.nom} — ${contexte}` : s.nom;
}

/** Le nom court d'une salle dans une liste déjà groupée par département : filière (si option) et niveau. */
export function libelleSalleDansDepartement(s: Salle): string {
  const departement = s.filiere?.departement;
  const filiere = s.filiere?.nom && s.filiere.nom !== departement?.nom ? s.filiere.nom : undefined;
  const contexte = [filiere, s.filiere?.niveau?.nom].filter(Boolean).join(" · ");

  return contexte ? `${s.nom} — ${contexte}` : s.nom;
}

export interface GroupeSalles {
  cle: string;
  titre: string;
  salles: Salle[];
}

/**
 * Les salles rangées par département puis par niveau, dans l'ordre où l'on
 * les cherche (GI · L1, GI · L2, … GRT · L1…), pour les listes déroulantes.
 * Les salles sans département (ne devrait plus arriver) ferment la marche.
 */
export function grouperSalles(salles: Salle[]): GroupeSalles[] {
  const groupes = new Map<string, GroupeSalles>();

  for (const s of [...salles].sort(comparerSalles)) {
    const departement = s.filiere?.departement;
    const cle = `${departement?.id ?? 0}`;
    const groupe = groupes.get(cle) ?? {
      cle,
      titre: departement ? `${departement.code} — ${departement.nom}` : "Sans département",
      salles: [],
    };
    groupe.salles.push(s);
    groupes.set(cle, groupe);
  }

  return [...groupes.values()];
}

function comparerSalles(a: Salle, b: Salle): number {
  return (
    (a.filiere?.departement?.code ?? "￿").localeCompare(b.filiere?.departement?.code ?? "￿") ||
    (a.filiere?.niveau?.nom ?? "").localeCompare(b.filiere?.niveau?.nom ?? "", undefined, { numeric: true }) ||
    (a.filiere?.nom ?? "").localeCompare(b.filiere?.nom ?? "") ||
    (a.formation === "FA" ? 1 : 0) - (b.formation === "FA" ? 1 : 0) ||
    a.nom.localeCompare(b.nom, undefined, { numeric: true })
  );
}
