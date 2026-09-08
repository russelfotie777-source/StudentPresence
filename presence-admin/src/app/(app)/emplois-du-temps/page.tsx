"use client";

import { useState } from "react";
import { motion } from "motion/react";
import { toast } from "sonner";
import {
  Plus,
  CalendarPlus,
  CalendarRange,
  Trash2,
  Sparkles,
  Inbox,
  AlertCircle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { matiereHooks, salleHooks, semaineHooks } from "@/hooks/use-catalog";
import {
  courseTemplateHooks,
  useEnseignants,
  useGenerateSeances,
  useGenerateSemester,
  type CourseTemplate,
} from "@/hooks/use-scheduling";
import { ApiError } from "@/lib/api-client";
import { libelleSalle } from "@/lib/catalogue";
import type { Weekday } from "@/types/api";

const JOURS: { valeur: Weekday; label: string }[] = [
  { valeur: "LUNDI", label: "Lundi" },
  { valeur: "MARDI", label: "Mardi" },
  { valeur: "MERCREDI", label: "Mercredi" },
  { valeur: "JEUDI", label: "Jeudi" },
  { valeur: "VENDREDI", label: "Vendredi" },
  { valeur: "SAMEDI", label: "Samedi" },
  { valeur: "DIMANCHE", label: "Dimanche" },
];

export default function EmploisDuTempsPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Emplois du temps
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Les semaines du semestre, puis les cours récurrents dont elles matérialisent les
          séances.
        </p>
      </div>

      <SectionSemaines />
      <SectionCoursRecurrents />
    </div>
  );
}

function SectionSemaines() {
  const { data: semaines, isLoading } = semaineHooks.useList();
  const generer = useGenerateSemester();
  const [dateDebut, setDateDebut] = useState("");
  const [nombre, setNombre] = useState(12);

  return (
    <section className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <CalendarRange className="size-4 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">Semaines du semestre</h2>
        {semaines && semaines.length > 0 && <Badge variant="secondary">{semaines.length}</Badge>}
      </div>

      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="date-debut" className="text-xs text-muted-foreground">
              1er lundi du semestre
            </Label>
            <Input
              id="date-debut"
              type="date"
              value={dateDebut}
              onChange={(e) => setDateDebut(e.target.value)}
              className="h-10 rounded-lg"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="nombre" className="text-xs text-muted-foreground">
              Nombre de semaines
            </Label>
            <Input
              id="nombre"
              type="number"
              min={1}
              max={52}
              value={nombre}
              onChange={(e) => setNombre(Number(e.target.value))}
              className="h-10 w-24 rounded-lg"
            />
          </div>
          <Button
            className="h-10 gap-1.5"
            onClick={() =>
              generer.mutate(
                { date_debut: dateDebut, nombre_semaines: nombre },
                {
                  onSuccess: (r) => toast.success(`${r.length} semaine(s) prête(s).`),
                  onError: (e) =>
                    toast.error(e instanceof ApiError ? e.message : "La génération a échoué."),
                },
              )
            }
            disabled={!dateDebut || generer.isPending}
          >
            <CalendarPlus className="size-4" />
            {generer.isPending ? "Génération…" : "Générer les semaines"}
          </Button>
        </div>

        {isLoading && <Skeleton className="h-6 w-64 rounded-lg" />}

        {semaines && semaines.length === 0 && (
          <p className="text-[13px] text-muted-foreground">
            Aucune semaine créée. Les cours récurrents ci-dessous n&apos;auront aucune séance à
            générer tant qu&apos;au moins une semaine n&apos;existe pas.
          </p>
        )}

        {semaines && semaines.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {semaines.map((s) => (
              <Badge key={s.id} variant="outline" className="tabular-nums">
                S{s.numero} · {dateSeule(s.date_debut)}
              </Badge>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}

function SectionCoursRecurrents() {
  const { data: templates, isLoading } = courseTemplateHooks.useList();
  const { data: matieres } = matiereHooks.useList();
  const { data: salles } = salleHooks.useList();
  const { data: enseignants } = useEnseignants();
  const { data: semaines } = semaineHooks.useList();
  const creer = courseTemplateHooks.useCreate();
  const supprimer = courseTemplateHooks.useRemove();
  const generer = useGenerateSeances();

  const [genereIdEnCours, setGenereIdEnCours] = useState<number | null>(null);
  const [aSupprimer, setASupprimer] = useState<CourseTemplate | null>(null);

  const [form, setForm] = useState({
    matiere_id: "",
    enseignant_id: "",
    salle_id: "",
    jour: "LUNDI" as Weekday,
    heure_debut: "08:00",
    heure_fin: "10:00",
    date_debut: "",
    date_fin: "",
  });

  const pretAEtreCree =
    form.matiere_id && form.enseignant_id && form.salle_id && form.date_debut && form.date_fin;

  function creerTemplate() {
    creer.mutate(
      {
        ...form,
        matiere_id: Number(form.matiere_id),
        enseignant_id: Number(form.enseignant_id),
        salle_id: Number(form.salle_id),
      },
      {
        onSuccess: () => {
          toast.success("Cours récurrent créé.");
          setForm((f) => ({ ...f, matiere_id: "", enseignant_id: "", salle_id: "" }));
        },
        onError: (e) =>
          toast.error(e instanceof ApiError ? e.message : "La création a échoué."),
      },
    );
  }

  function genererSeances(t: CourseTemplate) {
    setGenereIdEnCours(t.id);
    generer.mutate(t.id, {
      onSuccess: (r) => {
        toast.success(
          r.created.length > 0
            ? `${r.created.length} séance(s) créée(s)${r.skipped.length ? `, ${r.skipped.length} déjà existante(s)` : ""}.`
            : "Rien à générer : toutes les semaines couvertes ont déjà leur séance.",
        );
      },
      onError: (e) => toast.error(e instanceof ApiError ? e.message : "La génération a échoué."),
      onSettled: () => setGenereIdEnCours(null),
    });
  }

  return (
    <section className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <Sparkles className="size-4 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">Cours récurrents</h2>
        {templates && templates.length > 0 && <Badge variant="secondary">{templates.length}</Badge>}
      </div>

      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Champ label="Matière">
            <Select
              value={form.matiere_id}
              onValueChange={(v) => setForm((f) => ({ ...f, matiere_id: v ?? "" }))}
            >
              <SelectTrigger className="h-10 w-full rounded-lg">
                <SelectValue placeholder="Choisir…">
                  {() => matieres?.find((m) => String(m.id) === form.matiere_id)?.nom}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {matieres?.map((m) => (
                  <SelectItem key={m.id} value={String(m.id)}>
                    {m.nom} ({m.code})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Champ>

          <Champ label="Enseignant">
            <Select
              value={form.enseignant_id}
              onValueChange={(v) => setForm((f) => ({ ...f, enseignant_id: v ?? "" }))}
            >
              <SelectTrigger className="h-10 w-full rounded-lg">
                <SelectValue placeholder="Choisir…">
                  {() => enseignants?.find((e) => String(e.id) === form.enseignant_id)?.name}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {enseignants?.length === 0 && (
                  <p className="px-2.5 py-2 text-xs text-muted-foreground">
                    Aucun enseignant validé.
                  </p>
                )}
                {enseignants?.map((e) => (
                  <SelectItem key={e.id} value={String(e.id)}>
                    {e.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Champ>

          <Champ label="Salle">
            <Select
              value={form.salle_id}
              onValueChange={(v) => setForm((f) => ({ ...f, salle_id: v ?? "" }))}
            >
              <SelectTrigger className="h-10 w-full rounded-lg">
                <SelectValue placeholder="Choisir…">
                  {() => {
                    const s = salles?.find((s) => String(s.id) === form.salle_id);
                    return s && libelleSalle(s);
                  }}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {salles?.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    {libelleSalle(s)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Champ>

          <Champ label="Jour">
            <Select
              value={form.jour}
              onValueChange={(v) => setForm((f) => ({ ...f, jour: (v ?? "LUNDI") as Weekday }))}
            >
              <SelectTrigger className="h-10 w-full rounded-lg">
                <SelectValue>
                  {() => JOURS.find((j) => j.valeur === form.jour)?.label}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {JOURS.map((j) => (
                  <SelectItem key={j.valeur} value={j.valeur}>
                    {j.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Champ>

          <Champ label="Début">
            <Input
              type="time"
              value={form.heure_debut}
              onChange={(e) => setForm((f) => ({ ...f, heure_debut: e.target.value }))}
              className="h-10 rounded-lg"
            />
          </Champ>
          <Champ label="Fin">
            <Input
              type="time"
              value={form.heure_fin}
              onChange={(e) => setForm((f) => ({ ...f, heure_fin: e.target.value }))}
              className="h-10 rounded-lg"
            />
          </Champ>
          <Champ label="Valide à partir du">
            <Input
              type="date"
              value={form.date_debut}
              onChange={(e) => setForm((f) => ({ ...f, date_debut: e.target.value }))}
              className="h-10 rounded-lg"
            />
          </Champ>
          <Champ label="Jusqu'au">
            <Input
              type="date"
              value={form.date_fin}
              onChange={(e) => setForm((f) => ({ ...f, date_fin: e.target.value }))}
              className="h-10 rounded-lg"
            />
          </Champ>
        </div>

        {creer.error instanceof ApiError && (
          <div className="flex items-start gap-2.5 rounded-xl border border-destructive/25 bg-destructive/10 px-3.5 py-3 text-[13px] text-destructive">
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            <span>{creer.error.message}</span>
          </div>
        )}

        <Button
          onClick={creerTemplate}
          disabled={!pretAEtreCree || creer.isPending}
          className="w-fit gap-1.5"
        >
          <Plus className="size-4" />
          {creer.isPending ? "Création…" : "Créer le cours récurrent"}
        </Button>
      </div>

      {isLoading && (
        <div className="flex flex-col gap-2">
          {[...Array(2)].map((_, i) => (
            <Skeleton key={i} className="h-[72px] rounded-xl" />
          ))}
        </div>
      )}

      {templates && templates.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-10 text-center">
          <Inbox className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">Aucun cours récurrent pour l&apos;instant.</p>
        </div>
      )}

      <div className="flex flex-col gap-2">
        {templates?.map((t) => (
          <LigneTemplate
            key={t.id}
            template={t}
            enGeneration={genereIdEnCours === t.id}
            onGenerer={() => genererSeances(t)}
            onSupprimer={() => setASupprimer(t)}
            aucuneSemaine={!semaines || semaines.length === 0}
          />
        ))}
      </div>

      {aSupprimer && (
        <Dialog open onOpenChange={(o) => !o && setASupprimer(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Supprimer ce cours récurrent ?</DialogTitle>
              <DialogDescription>
                {aSupprimer.matiere?.nom} avec {aSupprimer.enseignant?.name} n&apos;engendrera
                plus de nouvelles séances. Les séances déjà générées restent, mais perdent le
                lien vers cette matière dans l&apos;historique.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setASupprimer(null)}>
                Annuler
              </Button>
              <Button
                variant="destructive"
                disabled={supprimer.isPending}
                onClick={() =>
                  supprimer.mutate(aSupprimer.id, {
                    onSuccess: () => toast.success("Cours récurrent supprimé."),
                    onSettled: () => setASupprimer(null),
                  })
                }
              >
                {supprimer.isPending ? "Suppression…" : "Supprimer"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </section>
  );
}

/**
 * Le backend sérialise les colonnes `date` en horodatage ISO complet
 * (2026-07-19T23:00:00.000000Z) : la partie horaire n'a aucun sens pour une
 * date de calendrier, ne garder que le jour.
 */
function dateSeule(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function Champ({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label className="text-xs text-muted-foreground">{label}</Label>
      {children}
    </div>
  );
}

function LigneTemplate({
  template: t,
  enGeneration,
  onGenerer,
  onSupprimer,
  aucuneSemaine,
}: {
  template: CourseTemplate;
  enGeneration: boolean;
  onGenerer: () => void;
  onSupprimer: () => void;
  aucuneSemaine: boolean;
}) {
  const jour = JOURS.find((j) => j.valeur === t.jour)?.label ?? t.jour;

  return (
    <motion.div
      initial={{ opacity: 0, y: 6 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
      className="flex flex-col gap-3 rounded-xl border border-border bg-card p-3.5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
    >
      <div className="min-w-0">
        <p className="truncate text-sm font-medium text-foreground">
          {t.matiere?.nom} · {t.enseignant?.name}
        </p>
        <p className="truncate text-xs text-muted-foreground">
          {t.salle?.nom} · {jour} {t.heure_debut.slice(0, 5)}–{t.heure_fin.slice(0, 5)} ·{" "}
          {dateSeule(t.date_debut)} → {dateSeule(t.date_fin)}
        </p>
      </div>
      <div className="flex shrink-0 gap-2">
        <Button
          size="sm"
          variant="secondary"
          className="gap-1.5"
          disabled={enGeneration || aucuneSemaine}
          title={aucuneSemaine ? "Générez d'abord au moins une semaine" : undefined}
          onClick={onGenerer}
        >
          <Sparkles className="size-3.5" />
          {enGeneration ? "Génération…" : "Générer les séances"}
        </Button>
        <Button
          size="icon-sm"
          variant="ghost"
          onClick={onSupprimer}
          aria-label={`Supprimer le cours récurrent ${t.matiere?.nom}`}
          className="text-muted-foreground hover:text-destructive"
        >
          <Trash2 className="size-4" />
        </Button>
      </div>
    </motion.div>
  );
}
