"use client";

import { useState } from "react";
import { ShieldBan, ShieldOff, Trash2, ArrowLeftRight, UserCheck, BadgeCheck } from "lucide-react";
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
import type { User } from "@/types/api";

/** Action demandée sur un compte : un seul dialogue ouvert à la fois. */
export type ActionEtudiant =
  | { type: "salle"; etudiant: User }
  | { type: "restreindre"; etudiant: User }
  | { type: "bloquer"; etudiant: User }
  | { type: "retablir"; etudiant: User }
  | { type: "presence_auto"; etudiant: User }
  | { type: "supprimer"; etudiant: User };

// --- Présence automatique (privilège admin) ---------------------------------

/**
 * Le privilège « toujours présent » : l'étudiant est compté présent à chaque
 * séance de sa salle, sans pointer, quoi que décide le délégué. C'est une
 * faveur qui pèse sur les listes officielles : on demande pourquoi.
 */
export function DialoguePresenceAuto({
  etudiant,
  enCours,
  onConfirmer,
  onFermer,
}: {
  etudiant: User;
  enCours: boolean;
  onConfirmer: (actif: boolean, motif?: string) => void;
  onFermer: () => void;
}) {
  const [motif, setMotif] = useState("");
  const actif = etudiant.presence_automatique === true;

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <BadgeCheck className="size-4 text-primary" />
            {actif ? "Retirer le privilège de" : "Compter toujours présent"} {etudiant.name}
          </DialogTitle>
          <DialogDescription>
            {actif
              ? `${etudiant.name} sera de nouveau appelé comme les autres : présent s'il pointe ou si le délégué le coche, absent sinon.`
              : "Il sera compté présent à chaque séance de sa salle, dès qu'elle commence, sans pointer et quel que soit l'appel du délégué. Une présence que vous posez vous-même sur une séance garde le dernier mot. Réversible à tout moment."}
          </DialogDescription>
        </DialogHeader>

        {!actif && (
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="motif-auto" className="text-xs text-muted-foreground">
              Motif — conservé avec la date, pour pouvoir l&apos;expliquer
            </Label>
            <Textarea
              id="motif-auto"
              rows={3}
              value={motif}
              onChange={(e) => setMotif(e.target.value)}
              placeholder="Ex. : stage en entreprise validé par la direction"
              className="rounded-lg"
              maxLength={500}
            />
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>Annuler</Button>
          <Button
            disabled={enCours || (!actif && motif.trim().length === 0)}
            onClick={() => onConfirmer(!actif, actif ? undefined : motif.trim())}
          >
            {enCours ? "Enregistrement…" : actif ? "Retirer le privilège" : "Compter présent"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
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
