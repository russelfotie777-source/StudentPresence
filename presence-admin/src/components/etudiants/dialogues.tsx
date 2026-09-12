"use client";

import { useState } from "react";
import { AlertTriangle, ShieldBan, ShieldOff, Trash2, ArrowLeftRight, UserCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { libelleSalle } from "@/lib/catalogue";
import type { Salle } from "@/hooks/use-catalog";
import type { PresenceState, Seance, User } from "@/types/api";
import { cn } from "@/lib/utils";

/** Action demandée depuis le menu d'une ligne : un seul dialogue ouvert à la fois. */
export type ActionEtudiant =
  | { type: "presence"; etudiant: User }
  | { type: "salle"; etudiant: User }
  | { type: "restreindre"; etudiant: User }
  | { type: "bloquer"; etudiant: User }
  | { type: "retablir"; etudiant: User }
  | { type: "supprimer"; etudiant: User };

const JOURS: Record<string, string> = {
  LUNDI: "Lun", MARDI: "Mar", MERCREDI: "Mer", JEUDI: "Jeu", VENDREDI: "Ven", SAMEDI: "Sam", DIMANCHE: "Dim",
};

function dateCourte(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "short" }) : "";
}

// --- Changer de salle -----------------------------------------------------

export function DialogueSalle({
  etudiant,
  salles,
  enCours,
  onConfirmer,
  onFermer,
}: {
  etudiant: User;
  salles: Salle[];
  enCours: boolean;
  onConfirmer: (salleId: number) => void;
  onFermer: () => void;
}) {
  const [salleId, setSalleId] = useState("");
  const candidates = salles.filter((s) => s.id !== etudiant.salle?.id);
  const cible = candidates.find((s) => String(s.id) === salleId);

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ArrowLeftRight className="size-4 text-primary" />
            Changer {etudiant.name} de salle
          </DialogTitle>
          <DialogDescription>
            Sa filière, son niveau et sa formation suivront la salle choisie — ce sont des
            propriétés de la salle, pas de l&apos;étudiant. Il apparaîtra sur la liste de
            présence de sa nouvelle salle dès la prochaine séance.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-1.5">
          <Label className="text-xs text-muted-foreground">
            Actuellement : {etudiant.salle?.nom ?? "aucune salle"}
            {etudiant.filiere && ` — ${etudiant.filiere.nom}`}
          </Label>
          <Select value={salleId} onValueChange={(v) => setSalleId(v ?? "")}>
            <SelectTrigger className="h-10 w-full rounded-lg">
              <SelectValue placeholder="Nouvelle salle…">
                {() => (cible ? libelleSalle(cible) : "Nouvelle salle…")}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {candidates.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {libelleSalle(s)} · {s.formation}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {cible && cible.formation !== etudiant.formation && (
            <p className="text-xs text-warning-foreground">
              Sa formation passera de {etudiant.formation} à {cible.formation}.
            </p>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>Annuler</Button>
          <Button disabled={!cible || enCours} onClick={() => cible && onConfirmer(cible.id)}>
            {enCours ? "Déplacement…" : "Changer de salle"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// --- Restreindre / bloquer (motif obligatoire) -----------------------------

export function DialogueSanction({
  etudiant,
  type,
  enCours,
  onConfirmer,
  onFermer,
}: {
  etudiant: User;
  type: "restreindre" | "bloquer";
  enCours: boolean;
  onConfirmer: (motif: string) => void;
  onFermer: () => void;
}) {
  const [motif, setMotif] = useState("");
  const bloquer = type === "bloquer";

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {bloquer ? (
              <ShieldBan className="size-4 text-destructive" />
            ) : (
              <ShieldOff className="size-4 text-warning-foreground" />
            )}
            {bloquer ? "Bloquer" : "Restreindre"} le compte de {etudiant.name}
          </DialogTitle>
          <DialogDescription>
            {bloquer
              ? "Il ne pourra plus se connecter, et sa session en cours sera coupée immédiatement. Il verra le motif à sa prochaine tentative."
              : "Il pourra toujours se connecter et consulter l'application, mais le pointage de présence lui sera refusé. Il en sera notifié avec le motif."}{" "}
            Réversible à tout moment.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="motif" className="text-xs text-muted-foreground">
            Motif — il sera montré à l&apos;étudiant
          </Label>
          <Textarea
            id="motif"
            rows={3}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
            placeholder={bloquer ? "Ex. : usurpation de compte signalée" : "Ex. : absences répétées non justifiées"}
            className="rounded-lg"
            maxLength={500}
          />
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>Annuler</Button>
          <Button
            variant="destructive"
            disabled={motif.trim().length < 3 || enCours}
            onClick={() => onConfirmer(motif.trim())}
          >
            {enCours ? "En cours…" : bloquer ? "Bloquer le compte" : "Restreindre le compte"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// --- Rétablir / supprimer (confirmation simple) ----------------------------

export function DialogueConfirmation({
  etudiant,
  type,
  enCours,
  onConfirmer,
  onFermer,
}: {
  etudiant: User;
  type: "retablir" | "supprimer";
  enCours: boolean;
  onConfirmer: () => void;
  onFermer: () => void;
}) {
  const supprimer = type === "supprimer";

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {supprimer ? (
              <Trash2 className="size-4 text-destructive" />
            ) : (
              <UserCheck className="size-4 text-success" />
            )}
            {supprimer ? "Supprimer" : "Rétablir"} le compte de {etudiant.name}
          </DialogTitle>
          <DialogDescription>
            {supprimer ? (
              <>
                Définitif. Son compte, son historique de présences et ses éventuelles demandes
                disparaissent avec lui. S&apos;il s&apos;agit d&apos;une sanction, préférez le
                blocage, qui est réversible.
              </>
            ) : (
              <>
                Il retrouve un compte actif : connexion et pointage normaux. Il en sera notifié.
              </>
            )}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>Annuler</Button>
          <Button variant={supprimer ? "destructive" : "default"} disabled={enCours} onClick={onConfirmer}>
            {enCours ? "En cours…" : supprimer ? "Supprimer définitivement" : "Rétablir"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// --- Forcer une présence ---------------------------------------------------

export function DialoguePresence({
  etudiant,
  seances,
  chargement,
  enCours,
  onConfirmer,
  onFermer,
}: {
  etudiant: User;
  seances: Seance[] | undefined;
  chargement: boolean;
  enCours: boolean;
  onConfirmer: (seanceId: number, etat: PresenceState) => void;
  onFermer: () => void;
}) {
  const [seanceId, setSeanceId] = useState<number | null>(null);
  const [etat, setEtat] = useState<PresenceState>("present");

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Forcer la présence de {etudiant.name}</DialogTitle>
          <DialogDescription>
            Passe outre la fenêtre horaire, le verrou du délégué et le périmètre GPS. Votre nom
            est enregistré avec la présence : elle restera distinguable d&apos;un pointage réel.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-3">
          <div className="flex gap-2">
            {(["present", "absent"] as const).map((e) => (
              <button
                key={e}
                type="button"
                onClick={() => setEtat(e)}
                className={cn(
                  "flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors",
                  etat === e
                    ? e === "present"
                      ? "border-success bg-success/10 text-success"
                      : "border-destructive bg-destructive/10 text-destructive"
                    : "border-border text-muted-foreground hover:bg-muted",
                )}
              >
                {e === "present" ? "Présent" : "Absent"}
              </button>
            ))}
          </div>

          <div className="flex max-h-72 flex-col gap-1.5 overflow-y-auto rounded-lg border border-border p-1.5">
            {chargement && <p className="p-3 text-sm text-muted-foreground">Chargement des séances…</p>}
            {seances?.length === 0 && (
              <p className="p-3 text-sm text-muted-foreground">Aucune séance enregistrée pour sa salle.</p>
            )}
            {seances?.map((s) => (
              <button
                key={s.id}
                type="button"
                onClick={() => setSeanceId(s.id)}
                className={cn(
                  "flex items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors",
                  seanceId === s.id ? "bg-primary/10 text-foreground" : "hover:bg-muted",
                )}
              >
                <span className="w-16 shrink-0 text-xs text-muted-foreground tabular-nums">
                  {JOURS[s.jour]} {dateCourte(s.date_seance)}
                </span>
                <span className="min-w-0 flex-1 truncate font-medium">{s.matiere ?? "Séance"}</span>
                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                  {s.heure_debut.slice(0, 5)}–{s.heure_fin.slice(0, 5)}
                </span>
                {s.presences_locked && (
                  <span className="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                    verrouillée
                  </span>
                )}
              </button>
            ))}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>Annuler</Button>
          <Button disabled={seanceId === null || enCours} onClick={() => seanceId && onConfirmer(seanceId, etat)}>
            {enCours ? "Enregistrement…" : `Marquer ${etat === "present" ? "présent" : "absent"}`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function AvertissementRestreint({ etudiant }: { etudiant: User }) {
  if (etudiant.statut_compte === "actif") return null;
  return (
    <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
      <AlertTriangle className="mt-0.5 size-3 shrink-0 text-warning-foreground" />
      <span>
        {etudiant.statut_compte === "bloque" ? "Bloqué" : "Restreint"}
        {etudiant.motif_statut && ` — ${etudiant.motif_statut}`}
      </span>
    </p>
  );
}
