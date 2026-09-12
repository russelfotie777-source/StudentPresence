"use client";

import { useState } from "react";
import { toast } from "sonner";
import {
  AlertCircle,
  CalendarX2,
  Clock,
  DoorOpen,
  GraduationCap,
  Lock,
  Pencil,
  Trash2,
  Users,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent } from "@/components/ui/sheet";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { Enseignant } from "@/hooks/use-scheduling";
import { useAnnulerSeance, useModifierSeance, useSupprimerCours } from "@/hooks/use-emploi-du-temps";
import { ApiError } from "@/lib/api-client";
import { formaterYmd, hhmm } from "@/lib/dates";
import { cn } from "@/lib/utils";
import type { Seance } from "@/types/api";
import { pastilleCours, seanceFigee } from "./constantes";
import { Selecteur } from "./dialogue-cours";

interface Props {
  seance: Seance;
  enseignants: Enseignant[];
  onFermer: () => void;
}

function messageErreur(error: unknown, repli: string): string {
  if (error instanceof ApiError) {
    return Object.values(error.errors ?? {}).flat()[0] ?? error.message;
  }
  return repli;
}

/**
 * Fiche d'une séance et ses retouches : décaler, changer d'enseignant,
 * annuler l'occurrence, ou supprimer tout le cours. Une séance tenue est
 * affichée mais figée — c'est de l'historique.
 */
export function PanneauSeance({ seance, enseignants, onFermer }: Props) {
  const figee = seanceFigee(seance);
  const [edition, setEdition] = useState(false);
  const [confirmation, setConfirmation] = useState<"seance" | "cours" | null>(null);

  const modifier = useModifierSeance();
  const annuler = useAnnulerSeance();
  const supprimerCours = useSupprimerCours();

  const [date, setDate] = useState(seance.date_seance ?? "");
  const [debut, setDebut] = useState(hhmm(seance.heure_debut));
  const [fin, setFin] = useState(hhmm(seance.heure_fin));
  const [enseignantId, setEnseignantId] = useState(String(seance.enseignant_id));

  function enregistrer() {
    modifier.mutate(
      {
        id: seance.id,
        data: {
          date_seance: date,
          heure_debut: debut,
          heure_fin: fin,
          enseignant_id: Number(enseignantId),
        },
      },
      {
        onSuccess: () => {
          toast.success("Séance modifiée.");
          onFermer();
        },
        onError: (e) => toast.error(messageErreur(e, "La modification a échoué.")),
      },
    );
  }

  function confirmer() {
    if (confirmation === "seance") {
      annuler.mutate(seance.id, {
        onSuccess: () => {
          toast.success("Séance annulée.");
          onFermer();
        },
        onError: (e) => toast.error(messageErreur(e, "L'annulation a échoué.")),
        onSettled: () => setConfirmation(null),
      });
    } else if (confirmation === "cours" && seance.course_template_id) {
      supprimerCours.mutate(seance.course_template_id, {
        onSuccess: (r) =>
          toast.success(
            r.seances_supprimees === 0
              ? "Cours supprimé ; aucune séance à venir n'était programmée."
              : `Cours supprimé, ${r.seances_supprimees} séance(s) à venir retirée(s).`,
          ),
        onError: (e) => toast.error(messageErreur(e, "La suppression a échoué.")),
        onSettled: () => {
          setConfirmation(null);
          onFermer();
        },
      });
    }
  }

  const dateLongue = seance.date_seance
    ? formaterYmd(seance.date_seance, { weekday: "long", day: "numeric", month: "long", year: "numeric" })
    : seance.jour;

  return (
    <Sheet open onOpenChange={(o) => !o && onFermer()}>
      <SheetContent side="right" className="w-full max-w-md gap-0 bg-card text-card-foreground sm:w-[26rem]">
        <div className="flex flex-col gap-5 overflow-y-auto p-6">
          <div className="pr-8">
            <div className="flex items-center gap-2">
              <span className={cn("size-2.5 rounded-full", pastilleCours(seance))} />
              <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                {seance.is_active ? "En cours" : seance.is_past ? "Séance passée" : "Séance à venir"}
              </p>
            </div>
            <h2 className="mt-1.5 font-display text-xl font-semibold leading-tight text-foreground">
              {seance.matiere ?? "Séance"}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground first-letter:uppercase">{dateLongue}</p>
          </div>

          <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2.5 text-sm">
            <Info icon={Clock} label="Horaire">
              {hhmm(seance.heure_debut)} – {hhmm(seance.heure_fin)}
              {seance.debut_reel && (
                <span className="ml-2 text-muted-foreground">
                  (réel : {hhmm(seance.debut_reel)}
                  {seance.fin_reelle ? ` – ${hhmm(seance.fin_reelle)}` : ""})
                </span>
              )}
            </Info>
            <Info icon={DoorOpen} label="Salle">
              {seance.salle} · groupe {seance.groupe}
            </Info>
            <Info icon={GraduationCap} label="Enseignant">
              {seance.enseignant}
            </Info>
            {(seance.is_past || figee) && (
              <Info icon={Users} label="Présences">
                <span className="flex flex-wrap items-center gap-1.5">
                  <Badge variant={seance.etat_final === "present" ? "secondary" : "destructive"}>
                    {seance.etat_final === "present" ? "Tenue" : "Non tenue"}
                  </Badge>
                  {seance.presences_locked && (
                    <Badge variant="outline" className="gap-1">
                      <Lock className="size-3" /> Appel verrouillé
                    </Badge>
                  )}
                  {(seance.presences_count ?? 0) > 0 && (
                    <span className="text-muted-foreground">
                      {seance.presences_count} pointage(s)
                    </span>
                  )}
                </span>
              </Info>
            )}
          </dl>

          {figee ? (
            <div className="flex items-start gap-2.5 rounded-xl border border-border bg-muted/60 px-3.5 py-3 text-[13px] text-muted-foreground">
              <Lock className="mt-0.5 size-4 shrink-0" />
              <span>
                Cette séance a été tenue ou a des présences enregistrées : elle fait partie de
                l&apos;historique et ne peut plus être modifiée ni annulée.
              </span>
            </div>
          ) : edition ? (
            <div className="flex flex-col gap-3 rounded-xl border border-border p-3.5">
              <div className="grid grid-cols-2 gap-3">
                <div className="col-span-2 flex flex-col gap-1.5">
                  <Label className="text-xs text-muted-foreground">Date</Label>
                  <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="h-10 rounded-lg" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label className="text-xs text-muted-foreground">Début</Label>
                  <Input type="time" step={300} value={debut} onChange={(e) => setDebut(e.target.value)} className="h-10 rounded-lg" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label className="text-xs text-muted-foreground">Fin</Label>
                  <Input type="time" step={300} value={fin} onChange={(e) => setFin(e.target.value)} className="h-10 rounded-lg" />
                </div>
                <div className="col-span-2 flex flex-col gap-1.5">
                  <Label className="text-xs text-muted-foreground">Enseignant</Label>
                  <Selecteur
                    valeur={enseignantId}
                    onChange={setEnseignantId}
                    options={enseignants.map((e) => ({ valeur: String(e.id), label: e.name }))}
                  />
                </div>
              </div>
              {modifier.error && (
                <div className="flex items-start gap-2 text-[13px] text-destructive">
                  <AlertCircle className="mt-0.5 size-4 shrink-0" />
                  <span>{messageErreur(modifier.error, "La modification a échoué.")}</span>
                </div>
              )}
              <div className="flex justify-end gap-2">
                <Button variant="outline" size="sm" onClick={() => setEdition(false)}>
                  Annuler
                </Button>
                <Button size="sm" onClick={enregistrer} disabled={modifier.isPending || !date || fin <= debut}>
                  {modifier.isPending ? "Enregistrement…" : "Enregistrer"}
                </Button>
              </div>
            </div>
          ) : (
            <div className="flex flex-col gap-2">
              <Button variant="outline" className="justify-start gap-2" onClick={() => setEdition(true)}>
                <Pencil className="size-4" />
                Modifier l&apos;horaire ou l&apos;enseignant
              </Button>
              <Button
                variant="outline"
                className="justify-start gap-2 text-destructive hover:text-destructive"
                onClick={() => setConfirmation("seance")}
              >
                <CalendarX2 className="size-4" />
                Annuler cette séance uniquement
              </Button>
              {seance.course_template_id && (
                <Button
                  variant="outline"
                  className="justify-start gap-2 text-destructive hover:text-destructive"
                  onClick={() => setConfirmation("cours")}
                >
                  <Trash2 className="size-4" />
                  Supprimer le cours et ses séances à venir
                </Button>
              )}
            </div>
          )}
        </div>
      </SheetContent>

      {confirmation && (
        <Dialog open onOpenChange={(o) => !o && setConfirmation(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>
                {confirmation === "seance" ? "Annuler cette séance ?" : "Supprimer tout le cours ?"}
              </DialogTitle>
              <DialogDescription>
                {confirmation === "seance"
                  ? `${seance.matiere ?? "La séance"} du ${dateLongue} disparaîtra de l'emploi du temps des étudiants. Les autres séances du cours ne bougent pas.`
                  : `${seance.matiere ?? "Ce cours"} avec ${seance.enseignant} n'aura plus de séances à venir dans cette salle. Les séances déjà tenues restent dans l'historique.`}
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setConfirmation(null)}>
                Retour
              </Button>
              <Button
                variant="destructive"
                onClick={confirmer}
                disabled={annuler.isPending || supprimerCours.isPending}
              >
                {annuler.isPending || supprimerCours.isPending
                  ? "Suppression…"
                  : confirmation === "seance"
                    ? "Annuler la séance"
                    : "Supprimer le cours"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </Sheet>
  );
}

function Info({
  icon: Icon,
  label,
  children,
}: {
  icon: typeof Clock;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <>
      <dt className="flex items-center gap-1.5 text-muted-foreground">
        <Icon className="size-3.5" />
        {label}
      </dt>
      <dd className="min-w-0 text-foreground">{children}</dd>
    </>
  );
}
