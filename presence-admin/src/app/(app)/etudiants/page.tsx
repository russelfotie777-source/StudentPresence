"use client";

import { useState } from "react";
import { motion } from "motion/react";
import {
  Search,
  MoreHorizontal,
  UserCheck,
  ArrowLeftRight,
  ShieldOff,
  ShieldBan,
  Trash2,
  ClipboardCheck,
  FileDown,
  Users,
  RotateCcw,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { salleHooks, semaineHooks, type Salle } from "@/hooks/use-catalog";
import {
  useChangerSalle,
  useChangerStatut,
  useEtudiants,
  useForcerPresence,
  useSeancesDeSalle,
  useSupprimerEtudiant,
  useTelechargerListe,
} from "@/hooks/use-etudiants";
import {
  AvertissementRestreint,
  DialogueConfirmation,
  DialoguePresence,
  DialogueSalle,
  DialogueSanction,
  type ActionEtudiant,
} from "@/components/etudiants/dialogues";
import { libelleSalle } from "@/lib/catalogue";
import type { StatutCompte, User } from "@/types/api";
import { cn } from "@/lib/utils";

const TOUTES = "toutes";
const TOUS = "tous";

const STATUTS: Record<StatutCompte, { label: string; classe: string }> = {
  actif: { label: "Actif", classe: "bg-success/15 text-success" },
  restreint: { label: "Restreint", classe: "bg-warning/20 text-warning-foreground" },
  bloque: { label: "Bloqué", classe: "bg-destructive/10 text-destructive" },
};

export default function EtudiantsPage() {
  const [search, setSearch] = useState("");
  const [salleId, setSalleId] = useState(TOUTES);
  const [statut, setStatut] = useState(TOUS);
  const [action, setAction] = useState<ActionEtudiant | null>(null);
  const [dialogueListe, setDialogueListe] = useState(false);

  const { data: salles } = salleHooks.useList();
  const requete = useEtudiants({
    search,
    salleId: salleId === TOUTES ? undefined : Number(salleId),
    statut: statut === TOUS ? undefined : (statut as StatutCompte),
  });

  const etudiants = requete.data?.pages.flatMap((p) => p.data) ?? [];
  const total = requete.data?.pages[0]?.meta.total ?? 0;
  const salleActive = salles?.find((s) => String(s.id) === salleId);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
            Étudiants
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Comptes inscrits, salle par salle. Rattachement, présence forcée, restriction ou
            blocage — et la liste de présence officielle à imprimer.
          </p>
        </div>
        <Button className="gap-1.5" onClick={() => setDialogueListe(true)}>
          <FileDown className="size-4" />
          Liste de présence
        </Button>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Nom ou matricule…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="h-10 rounded-lg pl-9"
          />
        </div>
        <Select value={salleId} onValueChange={(v) => setSalleId(v ?? TOUTES)}>
          <SelectTrigger className="h-10 w-full rounded-lg sm:w-64">
            <SelectValue placeholder="Toutes les salles">
              {() => (salleActive ? libelleSalle(salleActive) : "Toutes les salles")}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUTES}>Toutes les salles</SelectItem>
            {salles?.map((s) => (
              <SelectItem key={s.id} value={String(s.id)}>{libelleSalle(s)}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={statut} onValueChange={(v) => setStatut(v ?? TOUS)}>
          <SelectTrigger className="h-10 w-full rounded-lg sm:w-40">
            <SelectValue>
              {() => (statut === TOUS ? "Tous statuts" : STATUTS[statut as StatutCompte].label)}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Tous statuts</SelectItem>
            {(Object.keys(STATUTS) as StatutCompte[]).map((s) => (
              <SelectItem key={s} value={s}>{STATUTS[s].label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        {(salleId !== TOUTES || statut !== TOUS || search) && (
          <Button
            variant="ghost"
            size="icon"
            aria-label="Retirer les filtres"
            onClick={() => { setSearch(""); setSalleId(TOUTES); setStatut(TOUS); }}
          >
            <RotateCcw className="size-4" />
          </Button>
        )}
      </div>

      {requete.isLoading && (
        <div className="flex flex-col gap-2">
          {[...Array(6)].map((_, i) => <Skeleton key={i} className="h-[66px] rounded-xl" />)}
        </div>
      )}

      {!requete.isLoading && etudiants.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-14 text-center">
          <Users className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">Aucun étudiant ne correspond.</p>
        </div>
      )}

      {etudiants.length > 0 && (
        <p className="text-xs text-muted-foreground tabular-nums">
          {etudiants.length} sur {total}
        </p>
      )}

      <div className="flex flex-col gap-2">
        {etudiants.map((e) => (
          <LigneEtudiant key={e.id} etudiant={e} onAction={(type) => setAction({ type, etudiant: e } as ActionEtudiant)} />
        ))}
      </div>

      {requete.hasNextPage && (
        <div className="flex justify-center pt-1">
          <Button
            variant="outline"
            className="h-10 rounded-xl px-5"
            onClick={() => requete.fetchNextPage()}
            disabled={requete.isFetchingNextPage}
          >
            {requete.isFetchingNextPage ? "Chargement…" : "Voir plus"}
          </Button>
        </div>
      )}

      {action && <Dialogues action={action} salles={salles ?? []} onFermer={() => setAction(null)} />}
      {dialogueListe && <DialogueListePresence salles={salles ?? []} onFermer={() => setDialogueListe(false)} />}
    </div>
  );
}

function LigneEtudiant({
  etudiant: e,
  onAction,
}: {
  etudiant: User;
  onAction: (type: ActionEtudiant["type"]) => void;
}) {
  const statut = STATUTS[e.statut_compte];
  const fm = e.formation === "FM";

  return (
    <motion.div
      initial={{ opacity: 0, y: 6 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
      className={cn(
        "flex items-center gap-3 rounded-xl border bg-card p-3 shadow-xs",
        e.statut_compte === "actif" ? "border-border" : "border-warning/40",
      )}
    >
      <Avatar className="size-9 shrink-0">
        <AvatarFallback>{initiales(e.name)}</AvatarFallback>
      </Avatar>

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <p className="truncate text-sm font-semibold text-foreground">{e.name}</p>
          {e.role === "Delegue" && (
            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">Délégué</span>
          )}
          {e.formation && (
            <span
              className={cn(
                "rounded-full px-2 py-0.5 text-[10px] font-semibold",
                fm ? "bg-amber-100 text-amber-800 ring-1 ring-amber-300 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-700" : "bg-muted text-muted-foreground",
              )}
              title={fm ? "Formation migrante : venu de l'alternance, rattaché à une salle FI" : undefined}
            >
              {e.formation}
            </span>
          )}
          <span className={cn("rounded-full px-2 py-0.5 text-[10px] font-medium", statut.classe)}>{statut.label}</span>
        </div>
        <p className="mt-0.5 truncate text-xs text-muted-foreground">
          <span className="tabular-nums">{e.phone}</span>
          {e.salle && ` · ${e.salle.nom}`}
          {e.filiere && ` · ${e.filiere.nom}`}
          {e.niveau && ` · ${e.niveau.nom}`}
        </p>
        <AvertissementRestreint etudiant={e} />
      </div>

      <DropdownMenu>
        <DropdownMenuTrigger
          render={<Button variant="ghost" size="icon-sm" aria-label={`Actions pour ${e.name}`} />}
        >
          <MoreHorizontal className="size-4" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-56">
          <DropdownMenuItem onClick={() => onAction("presence")}>
            <ClipboardCheck className="size-4" /> Forcer une présence
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => onAction("salle")}>
            <ArrowLeftRight className="size-4" /> Changer de salle
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          {e.statut_compte !== "actif" && (
            <DropdownMenuItem onClick={() => onAction("retablir")}>
              <UserCheck className="size-4" /> Rétablir le compte
            </DropdownMenuItem>
          )}
          {e.statut_compte !== "restreint" && (
            <DropdownMenuItem onClick={() => onAction("restreindre")}>
              <ShieldOff className="size-4" /> Restreindre
            </DropdownMenuItem>
          )}
          {e.statut_compte !== "bloque" && (
            <DropdownMenuItem onClick={() => onAction("bloquer")}>
              <ShieldBan className="size-4" /> Bloquer
            </DropdownMenuItem>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem variant="destructive" onClick={() => onAction("supprimer")}>
            <Trash2 className="size-4" /> Supprimer le compte
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </motion.div>
  );
}

/** Un seul point de montage pour tous les dialogues d'action, selon ce qui est demandé. */
function Dialogues({ action, salles, onFermer }: { action: ActionEtudiant; salles: Salle[]; onFermer: () => void }) {
  const changerSalle = useChangerSalle();
  const changerStatut = useChangerStatut();
  const supprimer = useSupprimerEtudiant();
  const forcer = useForcerPresence();
  const seances = useSeancesDeSalle(action.type === "presence" ? action.etudiant.salle?.id : undefined);

  const fermerApres = { onSuccess: onFermer };

  switch (action.type) {
    case "salle":
      return (
        <DialogueSalle
          etudiant={action.etudiant}
          salles={salles}
          enCours={changerSalle.isPending}
          onConfirmer={(salle_id) => changerSalle.mutate({ id: action.etudiant.id, salle_id }, fermerApres)}
          onFermer={onFermer}
        />
      );
    case "restreindre":
    case "bloquer":
      return (
        <DialogueSanction
          etudiant={action.etudiant}
          type={action.type}
          enCours={changerStatut.isPending}
          onConfirmer={(motif) => changerStatut.mutate({ id: action.etudiant.id, action: action.type, motif }, fermerApres)}
          onFermer={onFermer}
        />
      );
    case "retablir":
      return (
        <DialogueConfirmation
          etudiant={action.etudiant}
          type="retablir"
          enCours={changerStatut.isPending}
          onConfirmer={() => changerStatut.mutate({ id: action.etudiant.id, action: "retablir" }, fermerApres)}
          onFermer={onFermer}
        />
      );
    case "supprimer":
      return (
        <DialogueConfirmation
          etudiant={action.etudiant}
          type="supprimer"
          enCours={supprimer.isPending}
          onConfirmer={() => supprimer.mutate(action.etudiant.id, fermerApres)}
          onFermer={onFermer}
        />
      );
    case "presence":
      return (
        <DialoguePresence
          etudiant={action.etudiant}
          seances={seances.data}
          chargement={seances.isLoading}
          enCours={forcer.isPending}
          onConfirmer={(seanceId, etat) =>
            forcer.mutate({ seanceId, etudiantId: action.etudiant.id, etat }, fermerApres)
          }
          onFermer={onFermer}
        />
      );
  }
}

/** Génération de la liste de présence hebdomadaire officielle d'une salle. */
function DialogueListePresence({ salles, onFermer }: { salles: Salle[]; onFermer: () => void }) {
  const { data: semaines } = semaineHooks.useList();
  const telecharger = useTelechargerListe();
  const [salleId, setSalleId] = useState("");
  const [semaineId, setSemaineId] = useState("");
  const [semestre, setSemestre] = useState("");
  const [annee, setAnnee] = useState("");

  const salle = salles.find((s) => String(s.id) === salleId);
  const semaine = semaines?.find((s) => String(s.id) === semaineId);

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <FileDown className="size-4 text-primary" />
            Liste de présence officielle
          </DialogTitle>
          <DialogDescription>
            Au format du département : en-tête bilingue, une colonne par jour, séances de la
            semaine préremplies. Les étudiants FM y sont signalés. Semestre et année sont
            déduits automatiquement — précisez-les seulement s&apos;ils diffèrent.
          </DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5 sm:col-span-2">
            <Label className="text-xs text-muted-foreground">Salle</Label>
            <Select value={salleId} onValueChange={(v) => setSalleId(v ?? "")}>
              <SelectTrigger className="h-10 w-full rounded-lg">
                <SelectValue placeholder="Choisir…">{() => (salle ? libelleSalle(salle) : "Choisir…")}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                {salles.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>{libelleSalle(s)} · {s.formation}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex flex-col gap-1.5 sm:col-span-2">
            <Label className="text-xs text-muted-foreground">Semaine</Label>
            <Select value={semaineId} onValueChange={(v) => setSemaineId(v ?? "")}>
              <SelectTrigger className="h-10 w-full rounded-lg">
                <SelectValue placeholder="Choisir…">
                  {() => (semaine ? `S${semaine.numero} · du ${dateFr(semaine.date_debut)} au ${dateFr(semaine.date_fin)}` : "Choisir…")}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {semaines?.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    S{s.numero} · du {dateFr(s.date_debut)} au {dateFr(s.date_fin)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs text-muted-foreground">Semestre (optionnel)</Label>
            <Input type="number" min={1} max={6} value={semestre} onChange={(e) => setSemestre(e.target.value)} placeholder="auto" className="h-10 rounded-lg" />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs text-muted-foreground">Année académique (optionnel)</Label>
            <Input value={annee} onChange={(e) => setAnnee(e.target.value)} placeholder="auto, ex. 2026-2027" className="h-10 rounded-lg" />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>Fermer</Button>
          <Button
            className="gap-1.5"
            disabled={!salle || !semaine || telecharger.isPending}
            onClick={() =>
              salle && semaine &&
              telecharger.mutate({
                salleId: salle.id,
                semaineId: semaine.id,
                semestre: semestre ? Number(semestre) : undefined,
                annee: annee || undefined,
              })
            }
          >
            <FileDown className="size-4" />
            {telecharger.isPending ? "Génération…" : "Télécharger le PDF"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function initiales(nom: string) {
  return nom.split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
}

function dateFr(iso: string) {
  return new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "short" });
}
