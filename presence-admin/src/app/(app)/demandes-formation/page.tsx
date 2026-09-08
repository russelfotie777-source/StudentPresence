"use client";

import { useState } from "react";
import { motion } from "motion/react";
import { ArrowRight, Check, X, Inbox, CalendarDays, MessageSquare } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  useApproveFormationRequest,
  useFormationRequests,
  useRejectFormationRequest,
} from "@/hooks/use-formation-requests";
import { salleHooks, type Salle } from "@/hooks/use-catalog";
import type { DemandeFormation, RequestStatus } from "@/types/api";
import { cn } from "@/lib/utils";

const STATUTS: Record<RequestStatus, { label: string; classe: string }> = {
  en_attente: { label: "En attente", classe: "bg-warning/20 text-warning-foreground" },
  acceptee: { label: "Acceptée", classe: "bg-success/15 text-success" },
  rejetee: { label: "Rejetée", classe: "bg-destructive/10 text-destructive" },
};

const VIDE: Record<RequestStatus, string> = {
  en_attente: "Aucune demande de migration en attente.",
  acceptee: "Aucune migration acceptée pour l'instant.",
  rejetee: "Aucune demande rejetée pour l'instant.",
};

export default function DemandesFormationPage() {
  const [onglet, setOnglet] = useState<RequestStatus>("en_attente");
  const { data: demandes, isLoading } = useFormationRequests(onglet);
  const { data: salles } = salleHooks.useList();

  const approuver = useApproveFormationRequest();
  const rejeter = useRejectFormationRequest();

  const [salleChoisie, setSalleChoisie] = useState<Record<number, string>>({});
  const [commentaires, setCommentaires] = useState<Record<number, string>>({});

  const idEnCours = approuver.isPending
    ? approuver.variables?.id
    : rejeter.isPending
      ? rejeter.variables?.id
      : undefined;

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Migrations FA → FI
        </h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Un étudiant en alternance demande à suivre l&apos;emploi du temps de jour.
          L&apos;approbation le bascule en FM et le rattache définitivement à la salle FI
          choisie, avec la filière et le niveau qui vont avec.
        </p>
      </div>

      <Tabs value={onglet} onValueChange={(v) => setOnglet(v as RequestStatus)}>
        <TabsList>
          <TabsTrigger value="en_attente">En attente</TabsTrigger>
          <TabsTrigger value="acceptee">Acceptées</TabsTrigger>
          <TabsTrigger value="rejetee">Rejetées</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {[...Array(2)].map((_, i) => (
            <Skeleton key={i} className="h-[170px] rounded-2xl" />
          ))}
        </div>
      )}

      {demandes?.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-14 text-center">
          <Inbox className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">{VIDE[onglet]}</p>
        </div>
      )}

      <motion.div
        initial="hidden"
        animate="show"
        variants={{ show: { transition: { staggerChildren: 0.05 } } }}
        className="flex flex-col gap-3"
      >
        {demandes?.map((d) => (
          <CarteDemande
            key={d.id}
            demande={d}
            salles={salles ?? []}
            salleChoisie={salleChoisie[d.id] ?? ""}
            onSalle={(v) => setSalleChoisie((c) => ({ ...c, [d.id]: v }))}
            commentaire={commentaires[d.id] ?? ""}
            onCommentaire={(v) => setCommentaires((c) => ({ ...c, [d.id]: v }))}
            onApprouver={() =>
              approuver.mutate({ id: d.id, salle_id: Number(salleChoisie[d.id]) })
            }
            onRejeter={() => rejeter.mutate({ id: d.id, commentaire: commentaires[d.id] })}
            enCours={idEnCours === d.id}
          />
        ))}
      </motion.div>
    </div>
  );
}

function CarteDemande({
  demande: d,
  salles,
  salleChoisie,
  onSalle,
  commentaire,
  onCommentaire,
  onApprouver,
  onRejeter,
  enCours,
}: {
  demande: DemandeFormation;
  salles: Salle[];
  salleChoisie: string;
  onSalle: (v: string) => void;
  commentaire: string;
  onCommentaire: (v: string) => void;
  onApprouver: () => void;
  onRejeter: () => void;
  enCours: boolean;
}) {
  const statut = STATUTS[d.statut];
  const cibles = ciblesPossibles(salles, d.etudiant?.niveau_id ?? null);

  return (
    <motion.article
      variants={{
        hidden: { opacity: 0, y: 8 },
        show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] } },
      }}
      className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs"
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          <Avatar className="size-9 shrink-0">
            <AvatarFallback>{initiales(d.etudiant?.name ?? "?")}</AvatarFallback>
          </Avatar>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-foreground">{d.etudiant?.name}</p>
            <p className="mt-0.5 truncate text-xs text-muted-foreground tabular-nums">
              {d.etudiant?.phone}
            </p>
          </div>
        </div>
        <span
          className={cn("shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium", statut.classe)}
        >
          {statut.label}
        </span>
      </div>

      <div className="flex flex-wrap items-center gap-2 text-[13px]">
        <Rattachement
          libelle="Aujourd'hui"
          valeur={[d.etudiant?.salle, d.etudiant?.filiere, d.etudiant?.niveau]
            .filter(Boolean)
            .join(" · ")}
        />
        <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
        <Rattachement
          libelle="Après migration"
          valeur={d.salle_cible?.nom ?? "à choisir"}
          enAttente={!d.salle_cible}
        />
      </div>

      {d.motif && (
        <p className="rounded-xl bg-muted/60 px-3.5 py-3 text-[13px] leading-relaxed text-foreground">
          « {d.motif} »
        </p>
      )}

      <p className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
        <CalendarDays className="size-3.5" />
        Déposée le {dateCourte(d.date_creation)}
      </p>

      {d.statut === "en_attente" && (
        <div className="flex flex-col gap-3 border-t border-border pt-3">
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <Select value={salleChoisie} onValueChange={(v) => onSalle(v ?? "")}>
              <SelectTrigger className="h-9 w-full rounded-lg sm:w-72">
                {/* Sans fonction de rendu, le déclencheur affiche l'id brut de
                    la salle choisie au lieu de son nom — comportement par
                    défaut du composant, pas un choix. */}
                <SelectValue placeholder="Salle FI d'accueil…">
                  {() => {
                    const choisie = cibles.find((c) => String(c.id) === salleChoisie);
                    return choisie ? libelleSalle(choisie) : "Salle FI d'accueil…";
                  }}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {cibles.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    {libelleSalle(s)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button
              size="sm"
              className="gap-1.5"
              disabled={!salleChoisie || enCours}
              onClick={onApprouver}
            >
              <Check className="size-3.5" />
              {enCours ? "Traitement…" : "Approuver"}
            </Button>
          </div>

          {cibles.length === 0 && (
            <p className="text-xs text-destructive">
              Aucune salle FI n&apos;existe pour ce niveau. Créez-la dans le catalogue avant
              d&apos;approuver.
            </p>
          )}

          <div className="flex flex-col gap-2">
            <Textarea
              placeholder="Motif du rejet, transmis à l'étudiant (optionnel)"
              value={commentaire}
              onChange={(e) => onCommentaire(e.target.value)}
              rows={2}
              className="resize-none rounded-xl"
            />
            <Button
              size="sm"
              variant="outline"
              className="w-fit gap-1.5"
              disabled={enCours}
              onClick={onRejeter}
            >
              <X className="size-3.5" />
              Rejeter la demande
            </Button>
          </div>
        </div>
      )}

      {d.commentaire_admin && (
        <div className="flex items-start gap-2 border-t border-border pt-3 text-xs text-muted-foreground">
          <MessageSquare className="mt-0.5 size-3.5 shrink-0" />
          <span>Votre note : {d.commentaire_admin}</span>
        </div>
      )}
    </motion.article>
  );
}

function Rattachement({
  libelle,
  valeur,
  enAttente = false,
}: {
  libelle: string;
  valeur: string;
  enAttente?: boolean;
}) {
  return (
    <span className="inline-flex min-w-0 flex-col rounded-xl border border-border px-3 py-1.5">
      <span className="text-[10px] tracking-wide text-muted-foreground uppercase">{libelle}</span>
      <span
        className={cn(
          "truncate text-[13px] font-medium",
          enAttente ? "text-muted-foreground/60 italic" : "text-foreground",
        )}
      >
        {valeur || "—"}
      </span>
    </span>
  );
}

/**
 * Salles FI proposées comme cible. Restreintes au niveau du demandeur : sans
 * ce filtre, l'admin arbitre parmi toutes les salles de l'établissement, dont
 * les noms se répètent d'une filière à l'autre. Repli sur toutes les salles
 * FI si le niveau est inconnu, pour ne jamais bloquer la décision.
 */
function ciblesPossibles(salles: Salle[], niveauId: number | null): Salle[] {
  const fi = salles.filter((s) => s.formation === "FI");
  if (niveauId === null) {
    return fi;
  }

  const duNiveau = fi.filter((s) => s.filiere?.niveau_id === niveauId);

  return duNiveau.length > 0 ? duNiveau : fi;
}

function libelleSalle(s: Salle): string {
  const contexte = [s.filiere?.nom, s.filiere?.niveau?.nom].filter(Boolean).join(" · ");

  return contexte ? `${s.nom} — ${contexte}` : s.nom;
}

function dateCourte(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function initiales(nom: string): string {
  return nom
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}
