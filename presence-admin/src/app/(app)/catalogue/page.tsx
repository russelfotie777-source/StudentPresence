"use client";

import { useMemo, useState } from "react";
import { motion } from "motion/react";
import { Plus, Trash2, Search, Layers, DoorOpen, BookOpen, GraduationCap } from "lucide-react";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  SuppressionDialog,
  type Consequence,
} from "@/components/catalogue/suppression-dialog";
import {
  filiereHooks,
  matiereHooks,
  niveauHooks,
  salleHooks,
  type Filiere,
  type Matiere,
  type Niveau,
  type Salle,
} from "@/hooks/use-catalog";

type Onglet = "niveaux" | "filieres" | "salles" | "matieres";

/** Valeur du choix « tous » — un Select ne peut pas porter une option vide. */
const TOUS = "tous";

export default function CataloguePage() {
  const [onglet, setOnglet] = useState<Onglet>("niveaux");

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Catalogue académique
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          La structure sur laquelle tout repose : un niveau contient des filières, qui
          contiennent des salles, où se tiennent les séances.
        </p>
      </div>

      <Tabs value={onglet} onValueChange={(v) => setOnglet(v as Onglet)}>
        <TabsList>
          <TabsTrigger value="niveaux">Niveaux</TabsTrigger>
          <TabsTrigger value="filieres">Filières</TabsTrigger>
          <TabsTrigger value="salles">Salles</TabsTrigger>
          <TabsTrigger value="matieres">Matières</TabsTrigger>
        </TabsList>
      </Tabs>

      {onglet === "niveaux" && <PanneauNiveaux />}
      {onglet === "filieres" && <PanneauFilieres />}
      {onglet === "salles" && <PanneauSalles />}
      {onglet === "matieres" && <PanneauMatieres />}
    </div>
  );
}

function PanneauNiveaux() {
  const { data: niveaux } = niveauHooks.useList();
  const { data: filieres } = filiereHooks.useList();
  const { data: salles } = salleHooks.useList();
  const creer = niveauHooks.useCreate();
  const supprimer = niveauHooks.useRemove();

  const [nom, setNom] = useState("");
  const [recherche, setRecherche] = useState("");
  const [aSupprimer, setASupprimer] = useState<Niveau | null>(null);

  const visibles = useFiltrage(niveaux, recherche, (n) => n.nom);

  // Cascade réelle : niveau → filières → salles. Comptée sur les données déjà
  // chargées, donc sans appel supplémentaire.
  const consequences = (niveau: Niveau): Consequence[] => {
    const filieresDuNiveau = filieres?.filter((f) => f.niveau_id === niveau.id) ?? [];
    const ids = new Set(filieresDuNiveau.map((f) => f.id));

    return [
      { libelle: "filières supprimées avec lui", nombre: filieresDuNiveau.length },
      {
        libelle: "salles supprimées avec elles",
        nombre: salles?.filter((s) => ids.has(s.filiere_id)).length ?? 0,
      },
    ];
  };

  return (
    <Panneau
      icon={Layers}
      formulaire={
        <>
          <Input
            placeholder="Ex : L3"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            className="h-10 flex-1 rounded-lg"
          />
          <Button
            className="h-10 gap-1.5"
            onClick={() => creer.mutate({ nom }, { onSuccess: () => setNom("") })}
            disabled={!nom || creer.isPending}
          >
            <Plus className="size-4" />
            Ajouter
          </Button>
        </>
      }
      recherche={niveaux && niveaux.length > 6 ? { valeur: recherche, set: setRecherche } : undefined}
      vide={visibles?.length === 0 ? "Aucun niveau." : undefined}
    >
      {visibles?.map((n) => (
        <Ligne key={n.id} titre={n.nom} onSupprimer={() => setASupprimer(n)} />
      ))}

      {aSupprimer && (
        <SuppressionDialog
          ouvert
          onOuvert={(o) => !o && setASupprimer(null)}
          type="le niveau"
          nom={aSupprimer.nom}
          consequences={consequences(aSupprimer)}
          enCours={supprimer.isPending}
          onConfirmer={() =>
            supprimer.mutate(aSupprimer.id, { onSettled: () => setASupprimer(null) })
          }
        />
      )}
    </Panneau>
  );
}

function PanneauFilieres() {
  const { data: niveaux } = niveauHooks.useList();
  const [niveauId, setNiveauId] = useState(TOUS);
  const { data: filieres } = filiereHooks.useList(
    niveauId !== TOUS ? `?niveau_id=${niveauId}` : "",
  );
  const { data: salles } = salleHooks.useList();
  const creer = filiereHooks.useCreate();
  const supprimer = filiereHooks.useRemove();

  const [nom, setNom] = useState("");
  const [recherche, setRecherche] = useState("");
  const [aSupprimer, setASupprimer] = useState<Filiere | null>(null);

  const visibles = useFiltrage(filieres, recherche, (f) => f.nom);

  return (
    <Panneau
      icon={GraduationCap}
      filtre={
        <Select value={niveauId} onValueChange={(v) => setNiveauId(v ?? TOUS)}>
          <SelectTrigger className="h-10 w-full rounded-lg sm:w-56">
            {/* Sans cette fonction, le déclencheur affiche la valeur brute
                sélectionnée ("tous" ou l'id numérique) au lieu de son libellé —
                c'est le comportement par défaut de ce composant, pas un choix. */}
            <SelectValue placeholder="Tous les niveaux">
              {() => (niveauId === TOUS ? "Tous les niveaux" : niveaux?.find((n) => String(n.id) === niveauId)?.nom)}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Tous les niveaux</SelectItem>
            {niveaux?.map((n) => (
              <SelectItem key={n.id} value={String(n.id)}>
                {n.nom}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      }
      formulaire={
        <>
          <Input
            placeholder="Nom de la filière"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            className="h-10 flex-1 rounded-lg"
          />
          <Button
            className="h-10 gap-1.5"
            onClick={() =>
              creer.mutate({ nom, niveau_id: Number(niveauId) }, { onSuccess: () => setNom("") })
            }
            disabled={!nom || niveauId === TOUS || creer.isPending}
          >
            <Plus className="size-4" />
            Ajouter
          </Button>
        </>
      }
      aide={
        niveauId === TOUS
          ? "Choisissez d'abord un niveau : une filière lui appartient nécessairement."
          : undefined
      }
      recherche={
        filieres && filieres.length > 6 ? { valeur: recherche, set: setRecherche } : undefined
      }
      vide={visibles?.length === 0 ? "Aucune filière." : undefined}
    >
      {visibles?.map((f) => (
        <Ligne
          key={f.id}
          titre={f.nom}
          detail={niveaux?.find((n) => n.id === f.niveau_id)?.nom}
          onSupprimer={() => setASupprimer(f)}
        />
      ))}

      {aSupprimer && (
        <SuppressionDialog
          ouvert
          onOuvert={(o) => !o && setASupprimer(null)}
          type="la filière"
          nom={aSupprimer.nom}
          consequences={[
            {
              libelle: "salles supprimées avec elle",
              nombre: salles?.filter((s) => s.filiere_id === aSupprimer.id).length ?? 0,
            },
          ]}
          enCours={supprimer.isPending}
          onConfirmer={() =>
            supprimer.mutate(aSupprimer.id, { onSettled: () => setASupprimer(null) })
          }
        />
      )}
    </Panneau>
  );
}

function PanneauSalles() {
  const { data: filieres } = filiereHooks.useList();
  const [filiereId, setFiliereId] = useState(TOUS);
  const { data: salles } = salleHooks.useList(
    filiereId !== TOUS ? `?filiere_id=${filiereId}` : "",
  );
  const creer = salleHooks.useCreate();
  const supprimer = salleHooks.useRemove();

  const [nom, setNom] = useState("");
  const [formation, setFormation] = useState("FI");
  const [recherche, setRecherche] = useState("");
  const [aSupprimer, setASupprimer] = useState<Salle | null>(null);

  const visibles = useFiltrage(salles, recherche, (s) => s.nom);

  return (
    <Panneau
      icon={DoorOpen}
      filtre={
        <Select value={filiereId} onValueChange={(v) => setFiliereId(v ?? TOUS)}>
          <SelectTrigger className="h-10 w-full rounded-lg sm:w-64">
            <SelectValue placeholder="Toutes les filières">
              {() => (filiereId === TOUS ? "Toutes les filières" : filieres?.find((f) => String(f.id) === filiereId)?.nom)}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Toutes les filières</SelectItem>
            {filieres?.map((f) => (
              <SelectItem key={f.id} value={String(f.id)}>
                {f.nom}
                {f.niveau?.nom ? ` — ${f.niveau.nom}` : ""}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      }
      formulaire={
        <>
          <Input
            placeholder="Nom de la salle"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            className="h-10 flex-1 rounded-lg"
          />
          <Select value={formation} onValueChange={(v) => setFormation(v ?? "FI")}>
            <SelectTrigger className="h-10 w-28 rounded-lg">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="FI">FI</SelectItem>
              <SelectItem value="FA">FA</SelectItem>
            </SelectContent>
          </Select>
          <Button
            className="h-10 gap-1.5"
            onClick={() =>
              creer.mutate(
                { nom, filiere_id: Number(filiereId), formation },
                { onSuccess: () => setNom("") },
              )
            }
            disabled={!nom || filiereId === TOUS || creer.isPending}
          >
            <Plus className="size-4" />
            Ajouter
          </Button>
        </>
      }
      aide={
        filiereId === TOUS
          ? "Choisissez d'abord une filière : une même salle physique existe en FI et en FA comme deux entrées distinctes."
          : undefined
      }
      recherche={salles && salles.length > 6 ? { valeur: recherche, set: setRecherche } : undefined}
      vide={visibles?.length === 0 ? "Aucune salle." : undefined}
    >
      {visibles?.map((s) => (
        <Ligne
          key={s.id}
          titre={s.nom}
          detail={[s.filiere?.nom, s.filiere?.niveau?.nom].filter(Boolean).join(" · ")}
          badge={s.formation}
          onSupprimer={() => setASupprimer(s)}
        />
      ))}

      {aSupprimer && (
        <SuppressionDialog
          ouvert
          onOuvert={(o) => !o && setASupprimer(null)}
          type="la salle"
          nom={aSupprimer.nom}
          consequences={[]}
          enCours={supprimer.isPending}
          onConfirmer={() =>
            supprimer.mutate(aSupprimer.id, { onSettled: () => setASupprimer(null) })
          }
        />
      )}
    </Panneau>
  );
}

function PanneauMatieres() {
  const { data: matieres } = matiereHooks.useList();
  const creer = matiereHooks.useCreate();
  const supprimer = matiereHooks.useRemove();

  const [nom, setNom] = useState("");
  const [code, setCode] = useState("");
  const [recherche, setRecherche] = useState("");
  const [aSupprimer, setASupprimer] = useState<Matiere | null>(null);

  const visibles = useFiltrage(matieres, recherche, (m) => `${m.nom} ${m.code}`);

  return (
    <Panneau
      icon={BookOpen}
      formulaire={
        <>
          <Input
            placeholder="Nom de la matière"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            className="h-10 flex-1 rounded-lg"
          />
          <Input
            placeholder="Code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            className="h-10 w-32 rounded-lg"
          />
          <Button
            className="h-10 gap-1.5"
            onClick={() =>
              creer.mutate(
                { nom, code },
                {
                  onSuccess: () => {
                    setNom("");
                    setCode("");
                  },
                },
              )
            }
            disabled={!nom || !code || creer.isPending}
          >
            <Plus className="size-4" />
            Ajouter
          </Button>
        </>
      }
      recherche={
        matieres && matieres.length > 6 ? { valeur: recherche, set: setRecherche } : undefined
      }
      vide={visibles?.length === 0 ? "Aucune matière." : undefined}
    >
      {visibles?.map((m) => (
        <Ligne key={m.id} titre={m.nom} badge={m.code} onSupprimer={() => setASupprimer(m)} />
      ))}

      {aSupprimer && (
        <SuppressionDialog
          ouvert
          onOuvert={(o) => !o && setASupprimer(null)}
          type="la matière"
          nom={aSupprimer.nom}
          consequences={[]}
          enCours={supprimer.isPending}
          onConfirmer={() =>
            supprimer.mutate(aSupprimer.id, { onSettled: () => setASupprimer(null) })
          }
        />
      )}
    </Panneau>
  );
}

function Panneau({
  icon: Icon,
  filtre,
  formulaire,
  aide,
  recherche,
  vide,
  children,
}: {
  icon: typeof Layers;
  filtre?: React.ReactNode;
  formulaire: React.ReactNode;
  aide?: string;
  recherche?: { valeur: string; set: (v: string) => void };
  vide?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        {filtre}
        <div className="flex flex-col gap-2 sm:flex-row">{formulaire}</div>
        {aide && <p className="text-xs text-muted-foreground">{aide}</p>}
      </div>

      {recherche && (
        <div className="relative max-w-sm">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Rechercher…"
            value={recherche.valeur}
            onChange={(e) => recherche.set(e.target.value)}
            className="h-10 rounded-xl pl-9"
          />
        </div>
      )}

      {vide ? (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-12 text-center">
          <Icon className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">{vide}</p>
        </div>
      ) : (
        <div className="flex flex-col gap-2">{children}</div>
      )}
    </div>
  );
}

function Ligne({
  titre,
  detail,
  badge,
  onSupprimer,
}: {
  titre: string;
  detail?: string;
  badge?: string;
  onSupprimer: () => void;
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-xs"
    >
      <div className="flex min-w-0 items-center gap-2.5">
        <span className="truncate text-sm font-medium text-foreground">{titre}</span>
        {badge && (
          <Badge variant="outline" className="shrink-0">
            {badge}
          </Badge>
        )}
        {detail && <span className="truncate text-xs text-muted-foreground">{detail}</span>}
      </div>
      <Button
        variant="ghost"
        size="icon-sm"
        onClick={onSupprimer}
        aria-label={`Supprimer ${titre}`}
        className="shrink-0 text-muted-foreground hover:text-destructive"
      >
        <Trash2 className="size-4" />
      </Button>
    </motion.div>
  );
}

function useFiltrage<T>(liste: T[] | undefined, terme: string, cle: (item: T) => string) {
  return useMemo(() => {
    if (!liste) {
      return undefined;
    }
    const recherche = terme.trim().toLowerCase();

    return recherche
      ? liste.filter((item) => cle(item).toLowerCase().includes(recherche))
      : liste;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [liste, terme]);
}
