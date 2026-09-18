"use client";

import { useMemo } from "react";
import { Building2 } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { departementHooks, type Salle } from "@/hooks/use-catalog";
import { grouperSalles, libelleSalle, libelleSalleDansDepartement } from "@/lib/catalogue";
import { cn } from "@/lib/utils";

/** Valeur du choix « tous » — un Select ne peut pas porter une option vide. */
export const TOUS_DEPARTEMENTS = "tous";

/**
 * Le département sur lequel un écran travaille : « tous » ou un seul. Un
 * admin du GI ne veut pas parcourir les salles du GRT pour trouver la
 * sienne ; et inversement, il doit pouvoir passer au GRT sans autre écran.
 */
export function SelecteurDepartement({
  valeur,
  onChange,
  className,
}: {
  valeur: string;
  onChange: (v: string) => void;
  className?: string;
}) {
  const { data: departements } = departementHooks.useList();
  const actif = departements?.find((d) => String(d.id) === valeur);

  return (
    <Select value={valeur} onValueChange={(v) => onChange(v ?? TOUS_DEPARTEMENTS)}>
      <SelectTrigger className={cn("h-9 w-full rounded-lg sm:w-56", className)}>
        <Building2 className="size-4 shrink-0 text-muted-foreground" />
        <SelectValue placeholder="Tous les départements">
          {() => (actif ? `${actif.code} — ${actif.nom}` : "Tous les départements")}
        </SelectValue>
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={TOUS_DEPARTEMENTS}>Tous les départements</SelectItem>
        {departements?.map((d) => (
          <SelectItem key={d.id} value={String(d.id)}>
            {d.code} — {d.nom}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

/** Les salles visibles sous un filtre de département (`TOUS_DEPARTEMENTS` : toutes). */
export function sallesDuDepartement(salles: Salle[] | undefined, departementId: string): Salle[] {
  return (salles ?? []).filter(
    (s) => departementId === TOUS_DEPARTEMENTS || String(s.filiere?.departement?.id ?? 0) === departementId,
  );
}

/**
 * La salle qu'un écran affiche : celle choisie si elle est dans le
 * département filtré, sinon la première du département — pour qu'un
 * changement de département montre aussitôt une classe plutôt qu'un vide.
 */
export function salleAffichee(salles: Salle[] | undefined, departementId: string, choisie: number | null): number | null {
  const visibles = grouperSalles(sallesDuDepartement(salles, departementId)).flatMap((g) => g.salles);

  if (choisie !== null && visibles.some((s) => s.id === choisie)) return choisie;

  return visibles[0]?.id ?? null;
}

/**
 * Choix d'une salle parmi celles d'un ou de tous les départements, rangées
 * département par département puis par niveau — la structure réelle, pas
 * une liste plate où A23 du GI voisine avec A23 du GRT.
 */
export function SelecteurSalle({
  salles,
  departementId,
  valeur,
  onChange,
  avecFormation = false,
  className,
}: {
  salles: Salle[] | undefined;
  /** `TOUS_DEPARTEMENTS` ou l'identifiant d'un département, en chaîne. */
  departementId?: string;
  valeur: number | null;
  onChange: (id: number | null) => void;
  /** Ajoute « · FI » / « · FA » au libellé — utile quand une salle existe dans les deux formations. */
  avecFormation?: boolean;
  className?: string;
}) {
  const filtre = departementId ?? TOUS_DEPARTEMENTS;

  const visibles = useMemo(() => sallesDuDepartement(salles, filtre), [salles, filtre]);
  const groupes = useMemo(() => grouperSalles(visibles), [visibles]);
  const unSeulDepartement = filtre !== TOUS_DEPARTEMENTS || groupes.length <= 1;
  // Dans la liste, l'en-tête de groupe porte déjà le département ; la valeur
  // affichée dans le déclencheur, elle, n'a pas ce contexte.
  const libelleChoisi = unSeulDepartement ? libelleSalleDansDepartement : libelleSalle;
  const suffixe = (s: Salle) => (avecFormation ? ` · ${s.formation}` : "");

  return (
    <Select value={valeur ? String(valeur) : ""} onValueChange={(v) => onChange(v ? Number(v) : null)}>
      <SelectTrigger className={cn("h-9 w-full rounded-lg sm:w-80", className)}>
        <SelectValue placeholder="Choisir une salle…">
          {() => {
            const s = salles?.find((s) => s.id === valeur);
            return s && `${libelleChoisi(s)}${suffixe(s)}`;
          }}
        </SelectValue>
      </SelectTrigger>
      <SelectContent>
        {salles?.length === 0 && (
          <p className="px-2.5 py-2 text-xs text-muted-foreground">
            Aucune salle : créez-en une dans le catalogue.
          </p>
        )}
        {salles && salles.length > 0 && visibles.length === 0 && (
          <p className="px-2.5 py-2 text-xs text-muted-foreground">Aucune salle dans ce département.</p>
        )}
        {groupes.map((g) => (
          <SelectGroup key={g.cle}>
            {!unSeulDepartement && <SelectLabel>{g.titre}</SelectLabel>}
            {g.salles.map((s) => (
              <SelectItem key={s.id} value={String(s.id)}>
                {libelleSalleDansDepartement(s)}
                {suffixe(s)}
              </SelectItem>
            ))}
          </SelectGroup>
        ))}
      </SelectContent>
    </Select>
  );
}
