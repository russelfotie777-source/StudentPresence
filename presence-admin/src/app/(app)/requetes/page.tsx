"use client";

import { useState } from "react";
import { motion } from "motion/react";
import {
  Check,
  X,
  Paperclip,
  Inbox,
  CalendarDays,
  Clock,
  AlertTriangle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useAdminRequetes, useProcessRequete } from "@/hooks/use-admin-requetes";
import type { RequestStatus, RequeteEnseignant } from "@/types/api";
import { cn } from "@/lib/utils";

// Jetons du thème plutôt que des couleurs figées : les anciennes valeurs
// (bg-amber-100/text-amber-800…) restaient claires en thème sombre, donc
// illisibles.
const STATUTS: Record<RequestStatus, { label: string; classe: string }> = {
  en_attente: { label: "En attente", classe: "bg-warning/20 text-warning-foreground" },
  acceptee: { label: "Acceptée", classe: "bg-success/15 text-success" },
  rejetee: { label: "Rejetée", classe: "bg-destructive/10 text-destructive" },
};

const VIDE: Record<RequestStatus, string> = {
  en_attente: "Aucune requête en attente. Rien ne vous bloque.",
  acceptee: "Aucune requête acceptée pour l'instant.",
  rejetee: "Aucune requête rejetée pour l'instant.",
};

export default function AdminRequetesPage() {
  const [onglet, setOnglet] = useState<RequestStatus>("en_attente");
  const { data: requetes, isLoading } = useAdminRequetes(onglet);
  const traiter = useProcessRequete();

  const [commentaires, setCommentaires] = useState<Record<number, string>>({});
  const [aAccepter, setAAccepter] = useState<RequeteEnseignant | null>(null);

  const idEnCours = traiter.isPending ? traiter.variables?.id : undefined;

  function envoyer(requete: RequeteEnseignant, action: "acceptee" | "rejetee") {
    traiter.mutate({ id: requete.id, action, commentaire: commentaires[requete.id] });
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Requêtes enseignants
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Contestations d&apos;une séance marquée absente. Accepter rétablit la présence et
          rend la séance payable.
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
            <Skeleton key={i} className="h-[150px] rounded-2xl" />
          ))}
        </div>
      )}

      {requetes?.length === 0 && (
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
        {requetes?.map((r) => (
          <CarteRequete
            key={r.id}
            requete={r}
            commentaire={commentaires[r.id] ?? ""}
            onCommentaire={(valeur) => setCommentaires((c) => ({ ...c, [r.id]: valeur }))}
            onAccepter={() => setAAccepter(r)}
            onRejeter={() => envoyer(r, "rejetee")}
            enCours={idEnCours === r.id}
          />
        ))}
      </motion.div>

      <Dialog open={aAccepter !== null} onOpenChange={(o) => !o && setAAccepter(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Accepter cette contestation ?</DialogTitle>
            <DialogDescription>
              La séance sera marquée présente pour l&apos;enseignant <em>et</em> pour le
              délégué, avec les horaires prévus comme heures réelles. Elle entrera donc dans
              les heures payées de {aAccepter?.enseignant}. Cette écriture ne se défait pas
              depuis cet écran.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAAccepter(null)}>
              Annuler
            </Button>
            <Button
              disabled={traiter.isPending}
              onClick={() => {
                if (aAccepter) {
                  envoyer(aAccepter, "acceptee");
                  setAAccepter(null);
                }
              }}
            >
              {traiter.isPending ? "Traitement…" : "Accepter et rétablir la présence"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function CarteRequete({
  requete: r,
  commentaire,
  onCommentaire,
  onAccepter,
  onRejeter,
  enCours,
}: {
  requete: RequeteEnseignant;
  commentaire: string;
  onCommentaire: (valeur: string) => void;
  onAccepter: () => void;
  onRejeter: () => void;
  enCours: boolean;
}) {
  const statut = STATUTS[r.statut];

  return (
    <motion.article
      variants={{
        hidden: { opacity: 0, y: 8 },
        show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] } },
      }}
      className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs"
    >
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-foreground">{r.enseignant}</p>
          <p className="mt-0.5 truncate text-[13px] text-muted-foreground">
            {r.matiere} · {r.salle} · {r.niveau}
          </p>
        </div>
        <span
          className={cn(
            "shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium",
            statut.classe,
          )}
        >
          {statut.label}
        </span>
      </div>

      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
        {r.heure_seance && (
          <span className="inline-flex items-center gap-1.5">
            <Clock className="size-3.5" />
            Séance de {r.heure_seance.slice(0, 5)}
          </span>
        )}
        <span className="inline-flex items-center gap-1.5">
          <CalendarDays className="size-3.5" />
          Déposée le {dateCourte(r.date_creation)}
        </span>
        <span className="text-muted-foreground/70">Séance n° {r.seance_id}</span>
      </div>

      <p className="rounded-xl bg-muted/60 px-3.5 py-3 text-[13px] leading-relaxed text-foreground">
        {r.description}
      </p>

      {r.preuve_url && (
        <a
          href={r.preuve_url}
          target="_blank"
          rel="noreferrer"
          className="inline-flex w-fit items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-[12.5px] font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
        >
          <Paperclip className="size-3.5" />
          Voir la pièce jointe
        </a>
      )}

      {r.statut === "en_attente" && (
        <div className="flex flex-col gap-2.5 border-t border-border pt-3">
          <Textarea
            placeholder="Commentaire transmis à l'enseignant (optionnel)"
            value={commentaire}
            onChange={(e) => onCommentaire(e.target.value)}
            rows={2}
            className="resize-none rounded-xl"
          />
          <div className="flex flex-wrap gap-2">
            <Button size="sm" className="gap-1.5" disabled={enCours} onClick={onAccepter}>
              <Check className="size-3.5" />
              Accepter
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="gap-1.5"
              disabled={enCours}
              onClick={onRejeter}
            >
              <X className="size-3.5" />
              {enCours ? "Traitement…" : "Rejeter"}
            </Button>
          </div>
        </div>
      )}

      {r.commentaire_admin && (
        <div className="flex items-start gap-2 border-t border-border pt-3 text-xs text-muted-foreground">
          <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
          <span>Votre note : {r.commentaire_admin}</span>
        </div>
      )}
    </motion.article>
  );
}

function dateCourte(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}
