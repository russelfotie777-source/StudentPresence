"use client";

import { useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { Building2, ChevronDown, DoorOpen, GraduationCap, Plus, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { SuppressionDialog } from "@/components/catalogue/suppression-dialog";
import {
  departementHooks,
  filiereHooks,
  niveauHooks,
  salleHooks,
  useArborescenceDepartement,
  type ArborescenceDepartement,
  type Departement,
} from "@/hooks/use-catalog";
import { cn } from "@/lib/utils";

/**
 * Les départements et, pour chacun, sa structure niveau par niveau : les
 * filières qu'il y a ouvertes et leurs salles. C'est ici qu'on bâtit un
 * département de bout en bout — le créer, le voir apparaître à chaque
 * niveau, y ajouter les salles — avant de programmer leurs emplois du temps.
 */
export function PanneauDepartements() {
  const { data: departements } = departementHooks.useList();
  const { data: niveaux } = niveauHooks.useList();
  const creer = departementHooks.useCreate();
  const supprimer = departementHooks.useRemove();

  const [nom, setNom] = useState("");
  const [code, setCode] = useState("");
  const [nomEn, setNomEn] = useState("");
  const [ouvert, setOuvert] = useState<number | null>(null);
  const [aSupprimer, setASupprimer] = useState<Departement | null>(null);

  const aucunNiveau = niveaux !== undefined && niveaux.length === 0;

  function ajouter() {
    creer.mutate(
      { nom: nom.trim(), code: code.trim().toUpperCase(), nom_en: nomEn.trim() || null },
      {
        onSuccess: (d) => {
          setNom("");
          setCode("");
          setNomEn("");
          setOuvert(d.id);
        },
      },
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        <div className="flex flex-col gap-2 sm:flex-row">
          <Input
            placeholder="Nom du département — ex : Génie Informatique"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            className="h-10 flex-1 rounded-lg"
          />
          <Input
            placeholder="Sigle — ex : GI"
            value={code}
            onChange={(e) => setCode(e.target.value.toUpperCase())}
            maxLength={10}
            className="h-10 rounded-lg sm:w-32"
          />
          <Input
            placeholder="Nom anglais (listes officielles)"
            value={nomEn}
            onChange={(e) => setNomEn(e.target.value)}
            className="h-10 flex-1 rounded-lg"
          />
          <Button
            className="h-10 gap-1.5"
            onClick={ajouter}
            disabled={!nom.trim() || !code.trim() || creer.isPending}
          >
            <Plus className="size-4" />
            Créer
          </Button>
        </div>
        <p className="text-xs text-muted-foreground">
          {aucunNiveau
            ? "Créez d'abord les niveaux (L1, L2, L3…) : un nouveau département s'ouvre à chacun d'eux."
            : `Un nouveau département s'ouvre d'office à chaque niveau (${niveaux?.map((n) => n.nom).join(", ")}) avec une filière à son nom : il ne reste qu'à y créer les salles. Le sigle et le nom anglais figurent en tête des listes de présence.`}
        </p>
      </div>

      {departements === undefined ? (
        <Skeleton className="h-32 w-full rounded-2xl" />
      ) : departements.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-12 text-center">
          <Building2 className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">Aucun département.</p>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          {departements.map((d) => (
            <CarteDepartement
              key={d.id}
              departement={d}
              ouvert={ouvert === d.id}
              onBasculer={() => setOuvert(ouvert === d.id ? null : d.id)}
              onSupprimer={() => setASupprimer(d)}
            />
          ))}
        </div>
      )}

      {aSupprimer && (
        <SuppressionDialog
          ouvert
          onOuvert={(o) => !o && setASupprimer(null)}
          type="le département"
          nom={aSupprimer.nom}
          consequences={[
            { libelle: "filières supprimées avec lui", nombre: aSupprimer.filieres_count ?? 0 },
            { libelle: "salles supprimées avec elles", nombre: aSupprimer.salles_count ?? 0 },
          ]}
          enCours={supprimer.isPending}
          onConfirmer={() =>
            supprimer.mutate(aSupprimer.id, { onSettled: () => setASupprimer(null) })
          }
        />
      )}
    </div>
  );
}

function CarteDepartement({
  departement,
  ouvert,
  onBasculer,
  onSupprimer,
}: {
  departement: Departement;
  ouvert: boolean;
  onBasculer: () => void;
  onSupprimer: () => void;
}) {
  const filieres = departement.filieres_count ?? 0;
  const salles = departement.salles_count ?? 0;

  return (
    <motion.div
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-xl border border-border bg-card shadow-xs"
    >
      <div className="flex items-center gap-3 px-4 py-3">
        <button
          type="button"
          onClick={onBasculer}
          aria-expanded={ouvert}
          className="flex min-w-0 flex-1 items-center gap-2.5 text-left"
        >
          <ChevronDown
            className={cn("size-4 shrink-0 text-muted-foreground transition-transform", ouvert && "rotate-180")}
          />
          <Badge className="shrink-0 font-display">{departement.code}</Badge>
          <span className="truncate text-sm font-medium text-foreground">{departement.nom}</span>
          {departement.nom_en && (
            <span className="hidden truncate text-xs text-muted-foreground sm:inline">{departement.nom_en}</span>
          )}
          <span className="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums">
            {filieres} filière{filieres > 1 ? "s" : ""} · {salles} salle{salles > 1 ? "s" : ""}
          </span>
        </button>
        <Button
          variant="ghost"
          size="icon-sm"
          onClick={onSupprimer}
          aria-label={`Supprimer ${departement.nom}`}
          className="shrink-0 text-muted-foreground hover:text-destructive"
        >
          <Trash2 className="size-4" />
        </Button>
      </div>

      <AnimatePresence initial={false}>
        {ouvert && (
          <motion.div
            key="arbre"
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="overflow-hidden"
          >
            <ArbreDepartement id={departement.id} />
          </motion.div>
        )}
      </AnimatePresence>
    </motion.div>
  );
}

/** Le département niveau par niveau, avec de quoi y ajouter une filière ou une salle sur place. */
function ArbreDepartement({ id }: { id: number }) {
  const { data: arbre } = useArborescenceDepartement(id);

  if (!arbre) {
    return (
      <div className="border-t border-border px-4 py-3">
        <Skeleton className="h-16 w-full rounded-lg" />
      </div>
    );
  }

  return (
    <div className="flex flex-col divide-y divide-border border-t border-border">
      {arbre.niveaux.length === 0 && (
        <p className="px-4 py-3 text-xs text-muted-foreground">
          Aucun niveau défini : créez-les dans l&apos;onglet Niveaux.
        </p>
      )}
      {arbre.niveaux.map((niveau) => (
        <LigneNiveau key={niveau.id} arbre={arbre} niveau={niveau} />
      ))}
    </div>
  );
}

function LigneNiveau({
  arbre,
  niveau,
}: {
  arbre: ArborescenceDepartement;
  niveau: ArborescenceDepartement["niveaux"][number];
}) {
  const creerFiliere = filiereHooks.useCreate();
  const [nouvelle, setNouvelle] = useState<string | null>(null);

  function ajouterFiliere() {
    if (!nouvelle?.trim()) return;
    creerFiliere.mutate(
      { nom: nouvelle.trim(), niveau_id: niveau.id, departement_id: arbre.id },
      { onSuccess: () => setNouvelle(null) },
    );
  }

  return (
    <div className="flex gap-3 px-4 py-2.5">
      <div className="w-10 shrink-0 pt-1 font-display text-sm font-semibold text-foreground">{niveau.nom}</div>
      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        {niveau.filieres.length === 0 && nouvelle === null && (
          <p className="pt-1 text-xs text-muted-foreground">Aucune filière à ce niveau.</p>
        )}
        {niveau.filieres.map((f) => (
          <LigneFiliere key={f.id} filiere={f} troncCommun={f.nom === arbre.nom} />
        ))}
        {nouvelle !== null ? (
          <div className="flex items-center gap-2">
            <Input
              autoFocus
              placeholder="Nom de la filière — ex : ASR"
              value={nouvelle}
              onChange={(e) => setNouvelle(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") ajouterFiliere();
                if (e.key === "Escape") setNouvelle(null);
              }}
              className="h-8 max-w-xs rounded-lg text-sm"
            />
            <Button size="sm" className="h-8" onClick={ajouterFiliere} disabled={!nouvelle.trim() || creerFiliere.isPending}>
              Ajouter
            </Button>
            <Button size="sm" variant="ghost" className="h-8" onClick={() => setNouvelle(null)}>
              Annuler
            </Button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setNouvelle("")}
            className="flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
          >
            <Plus className="size-3" /> filière en {niveau.nom}
          </button>
        )}
      </div>
    </div>
  );
}

function LigneFiliere({
  filiere,
  troncCommun,
}: {
  filiere: ArborescenceDepartement["niveaux"][number]["filieres"][number];
  troncCommun: boolean;
}) {
  const creerSalle = salleHooks.useCreate();
  const [nouvelle, setNouvelle] = useState<{ nom: string; formation: "FI" | "FA" } | null>(null);

  function ajouterSalle() {
    if (!nouvelle?.nom.trim()) return;
    creerSalle.mutate(
      { nom: nouvelle.nom.trim(), filiere_id: filiere.id, formation: nouvelle.formation },
      { onSuccess: () => setNouvelle(null) },
    );
  }

  return (
    <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
      <span className="flex items-center gap-1.5 text-[13px] text-foreground">
        <GraduationCap className="size-3.5 text-muted-foreground" />
        {filiere.nom}
        {troncCommun && <span className="text-xs text-muted-foreground">(tronc commun)</span>}
      </span>
      {filiere.salles.map((s) => (
        <Badge key={s.id} variant="outline" className="gap-1 font-normal">
          <DoorOpen className="size-3 text-muted-foreground" />
          {s.nom}
          <span className={cn("text-[10px] font-semibold", s.formation === "FA" ? "text-warning-foreground" : "text-muted-foreground")}>
            {s.formation}
          </span>
        </Badge>
      ))}
      {nouvelle ? (
        <span className="flex items-center gap-1.5">
          <Input
            autoFocus
            placeholder="Salle — ex : A23"
            value={nouvelle.nom}
            onChange={(e) => setNouvelle({ ...nouvelle, nom: e.target.value })}
            onKeyDown={(e) => {
              if (e.key === "Enter") ajouterSalle();
              if (e.key === "Escape") setNouvelle(null);
            }}
            className="h-8 w-36 rounded-lg text-sm"
          />
          <Select
            value={nouvelle.formation}
            onValueChange={(v) => setNouvelle({ ...nouvelle, formation: (v as "FI" | "FA") ?? "FI" })}
          >
            <SelectTrigger className="h-8 w-20 rounded-lg text-sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="FI">FI</SelectItem>
              <SelectItem value="FA">FA</SelectItem>
            </SelectContent>
          </Select>
          <Button size="sm" className="h-8" onClick={ajouterSalle} disabled={!nouvelle.nom.trim() || creerSalle.isPending}>
            Ajouter
          </Button>
          <Button size="sm" variant="ghost" className="h-8" onClick={() => setNouvelle(null)}>
            Annuler
          </Button>
        </span>
      ) : (
        <button
          type="button"
          onClick={() => setNouvelle({ nom: "", formation: "FI" })}
          className="flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
        >
          <Plus className="size-3" /> salle
        </button>
      )}
    </div>
  );
}
