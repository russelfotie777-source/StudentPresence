"use client";

import { useMemo, useState } from "react";
import {
  CalendarRange,
  ChevronLeft,
  ChevronRight,
  Clock,
  DoorOpen,
  GraduationCap,
  Lock,
  Plus,
  Sparkles,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { matiereHooks, salleHooks, semaineHooks } from "@/hooks/use-catalog";
import { useEnseignants } from "@/hooks/use-scheduling";
import { useEmploiDuTemps, type ModeGrille } from "@/hooks/use-emploi-du-temps";
import { useHeureDouala } from "@/hooks/use-heure";
import { libelleSalle } from "@/lib/catalogue";
import { FUSEAU, minutes, plageSemaine, type Ymd } from "@/lib/dates";
import { cn } from "@/lib/utils";
import type { Seance, Weekday } from "@/types/api";
import { GrilleSemaine } from "@/components/emploi-du-temps/grille-semaine";
import { DialogueCours, type PreremplissageCours } from "@/components/emploi-du-temps/dialogue-cours";
import { DialogueSemestre } from "@/components/emploi-du-temps/dialogue-semestre";
import { PanneauSeance } from "@/components/emploi-du-temps/panneau-seance";
import { pastilleCours } from "@/components/emploi-du-temps/constantes";

export default function EmploisDuTempsPage() {
  const { data: salles } = salleHooks.useList();
  const { data: semaines, isLoading: semainesEnChargement } = semaineHooks.useList();
  const { data: matieres } = matiereHooks.useList();
  const { data: enseignants } = useEnseignants();
  const heure = useHeureDouala();

  const [mode, setMode] = useState<ModeGrille>("salle");
  const [salleChoisie, setSalleChoisie] = useState<number | null>(null);
  const [enseignantChoisi, setEnseignantChoisi] = useState<number | null>(null);
  const [semaineChoisie, setSemaineChoisie] = useState<number | null>(null);

  // Par défaut la première salle : l'onglet montre tout de suite quelque
  // chose au lieu d'un sélecteur vide à remplir.
  const salleId = salleChoisie ?? salles?.[0]?.id ?? null;
  const enseignantId = enseignantChoisi ?? enseignants?.[0]?.id ?? null;
  const cibleId = mode === "salle" ? salleId : enseignantId;

  const grille = useEmploiDuTemps(mode, cibleId, semaineChoisie);
  const semaine = grille.data?.semaine ?? null;
  const seances = useMemo(() => grille.data?.data ?? [], [grille.data]);

  const [dialogueCours, setDialogueCours] = useState<PreremplissageCours | null>(null);
  const [dialogueSemestre, setDialogueSemestre] = useState(false);
  const [seanceOuverte, setSeanceOuverte] = useState<Seance | null>(null);

  const semainesTriees = useMemo(
    () => [...(semaines ?? [])].sort((a, b) => a.numero - b.numero),
    [semaines],
  );
  const indexSemaine = semaine ? semainesTriees.findIndex((s) => s.id === semaine.id) : -1;
  const precedente = indexSemaine > 0 ? semainesTriees[indexSemaine - 1] : null;
  const suivante =
    indexSemaine >= 0 && indexSemaine < semainesTriees.length - 1
      ? semainesTriees[indexSemaine + 1]
      : null;
  const dateDuJour = heure.date;
  const semaineDuJour = dateDuJour
    ? (semainesTriees.find((s) => s.date_debut <= dateDuJour && dateDuJour <= s.date_fin) ?? null)
    : null;

  const matieresDeLaSemaine = useMemo(() => {
    const vues = new Map<string, Seance>();
    for (const s of seances) {
      const cle = String(s.matiere_id ?? s.matiere ?? s.id);
      if (!vues.has(cle)) vues.set(cle, s);
    }
    return [...vues.values()];
  }, [seances]);
  const totalMinutes = seances.reduce((t, s) => t + minutes(s.heure_fin) - minutes(s.heure_debut), 0);

  function ouvrirCreneau(date: Ymd, jour: Weekday, heureDebut: string) {
    setDialogueCours({
      salle_id: mode === "salle" ? (salleId ?? undefined) : undefined,
      enseignant_id: mode === "enseignant" ? (enseignantId ?? undefined) : undefined,
      jour,
      date,
      heure_debut: heureDebut,
    });
  }

  const aucuneSemaine = !semainesEnChargement && semainesTriees.length === 0;

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
            Emplois du temps
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            La semaine de chaque salle, telle que les étudiants la verront. Cliquez un créneau
            libre pour y poser un cours, une séance pour la retoucher.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <HorlogeDouala heure={heure} />
          <Button variant="outline" className="gap-1.5" onClick={() => setDialogueSemestre(true)}>
            <CalendarRange className="size-4" />
            Calendrier du semestre
          </Button>
          <Button
            className="gap-1.5"
            disabled={aucuneSemaine}
            onClick={() =>
              setDialogueCours({
                salle_id: mode === "salle" ? (salleId ?? undefined) : undefined,
                enseignant_id: mode === "enseignant" ? (enseignantId ?? undefined) : undefined,
              })
            }
          >
            <Plus className="size-4" />
            Programmer un cours
          </Button>
        </div>
      </div>

      {aucuneSemaine ? (
        <GuideDemarrage onCalendrier={() => setDialogueSemestre(true)} />
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <Tabs value={mode} onValueChange={(v) => setMode(v as ModeGrille)}>
                <TabsList>
                  <TabsTrigger value="salle">
                    <DoorOpen className="size-3.5" /> Par salle
                  </TabsTrigger>
                  <TabsTrigger value="enseignant">
                    <GraduationCap className="size-3.5" /> Par enseignant
                  </TabsTrigger>
                </TabsList>
              </Tabs>

              {mode === "salle" ? (
                <Select
                  value={salleId ? String(salleId) : ""}
                  onValueChange={(v) => setSalleChoisie(v ? Number(v) : null)}
                >
                  <SelectTrigger className="h-9 w-full rounded-lg sm:w-80">
                    <SelectValue placeholder="Choisir une salle…">
                      {() => {
                        const s = salles?.find((s) => s.id === salleId);
                        return s && libelleSalle(s);
                      }}
                    </SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    {salles?.length === 0 && (
                      <p className="px-2.5 py-2 text-xs text-muted-foreground">
                        Aucune salle : créez-en une dans le catalogue.
                      </p>
                    )}
                    {salles?.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>
                        {libelleSalle(s)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <Select
                  value={enseignantId ? String(enseignantId) : ""}
                  onValueChange={(v) => setEnseignantChoisi(v ? Number(v) : null)}
                >
                  <SelectTrigger className="h-9 w-full rounded-lg sm:w-72">
                    <SelectValue placeholder="Choisir un enseignant…">
                      {() => enseignants?.find((e) => e.id === enseignantId)?.name}
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
              )}
            </div>

            <div className="flex items-center gap-1.5">
              <Button
                size="icon"
                variant="outline"
                aria-label="Semaine précédente"
                disabled={!precedente}
                onClick={() => precedente && setSemaineChoisie(precedente.id)}
              >
                <ChevronLeft className="size-4" />
              </Button>
              <Select
                value={semaine ? String(semaine.id) : ""}
                onValueChange={(v) => v && setSemaineChoisie(Number(v))}
              >
                <SelectTrigger className="h-8 w-52 rounded-lg font-medium">
                  <SelectValue placeholder="Semaine…">
                    {() =>
                      semaine &&
                      `S${semaine.numero} · ${plageSemaine(semaine.date_debut, semaine.date_fin)}`
                    }
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  {semainesTriees.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      S{s.numero} · {plageSemaine(s.date_debut, s.date_fin)}
                      {s.id === semaineDuJour?.id ? " · cette semaine" : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button
                size="icon"
                variant="outline"
                aria-label="Semaine suivante"
                disabled={!suivante}
                onClick={() => suivante && setSemaineChoisie(suivante.id)}
              >
                <ChevronRight className="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                disabled={!semaineDuJour || semaineDuJour.id === semaine?.id}
                onClick={() => semaineDuJour && setSemaineChoisie(semaineDuJour.id)}
              >
                Aujourd&apos;hui
              </Button>
            </div>
          </div>

          {grille.isLoading || !semaine ? (
            <Skeleton className="h-[560px] w-full rounded-2xl" />
          ) : (
            <>
              {seances.length === 0 && (
                <div className="flex items-center gap-2.5 rounded-xl border border-dashed border-border px-4 py-3 text-[13px] text-muted-foreground">
                  <Sparkles className="size-4 shrink-0" />
                  Aucune séance cette semaine
                  {mode === "salle" ? " dans cette salle" : " pour cet enseignant"}. Cliquez sur un
                  créneau de la grille pour y programmer un cours.
                </div>
              )}
              <GrilleSemaine
                semaine={semaine}
                seances={seances}
                mode={mode}
                maintenant={heure.pret ? { date: heure.date, minute: heure.minute } : null}
                onSeance={setSeanceOuverte}
                onCreneau={ouvrirCreneau}
              />
              <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-1 text-[12.5px] text-muted-foreground">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                  {matieresDeLaSemaine.map((s) => (
                    <span key={s.id} className="flex items-center gap-1.5">
                      <span className={cn("size-2.5 rounded-full", pastilleCours(s))} />
                      {s.matiere ?? "Séance"}
                    </span>
                  ))}
                  {seances.length > 0 && (
                    <>
                      <span className="flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-success" /> en cours
                      </span>
                      <span className="flex items-center gap-1">
                        <Lock className="size-3" /> tenue, figée
                      </span>
                    </>
                  )}
                </div>
                <span className="tabular-nums">
                  {seances.length} séance{seances.length > 1 ? "s" : ""} ·{" "}
                  {Math.round((totalMinutes / 60) * 10) / 10} h de cours
                </span>
              </div>
            </>
          )}
        </>
      )}

      {dialogueCours && salles && matieres && enseignants && (
        <DialogueCours
          prerempli={dialogueCours}
          salles={salles}
          matieres={matieres}
          enseignants={enseignants}
          semaines={semainesTriees}
          semaineCourante={semaine ?? semaineDuJour}
          onFermer={() => setDialogueCours(null)}
        />
      )}

      {dialogueSemestre && (
        <DialogueSemestre
          semaines={semainesTriees}
          semaineCouranteId={semaineDuJour?.id ?? null}
          onFermer={() => setDialogueSemestre(false)}
        />
      )}

      {seanceOuverte && (
        <PanneauSeance
          seance={seanceOuverte}
          enseignants={enseignants ?? []}
          onFermer={() => setSeanceOuverte(null)}
        />
      )}
    </div>
  );
}

/**
 * L'heure qui fait foi : celle de Douala, servie par l'API. Rendue ici pour
 * qu'un admin dont la machine est réglée sur un autre fuseau voie tout de
 * suite sur quelle horloge la grille s'aligne.
 */
function HorlogeDouala({ heure }: { heure: ReturnType<typeof useHeureDouala> }) {
  if (!heure.pret) return null;

  return (
    <span
      className="flex h-8 items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 text-[12.5px] tabular-nums text-muted-foreground"
      title={`Heure de référence de l'application (${heure.fuseau})`}
    >
      <Clock className="size-3.5" />
      <span className="first-letter:uppercase">
        {heure.maintenant.toLocaleDateString("fr-FR", {
          timeZone: FUSEAU,
          weekday: "short",
          day: "numeric",
          month: "short",
        })}
      </span>
      <span className="font-semibold text-foreground">
        {heure.maintenant.toLocaleTimeString("fr-FR", {
          timeZone: FUSEAU,
          hour: "2-digit",
          minute: "2-digit",
        })}
      </span>
      <span className="text-[10.5px] uppercase tracking-wide">Douala</span>
    </span>
  );
}

function GuideDemarrage({ onCalendrier }: { onCalendrier: () => void }) {
  return (
    <div className="flex flex-col items-center gap-5 rounded-2xl border border-dashed border-border bg-card px-6 py-12 text-center">
      <div className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
        <CalendarRange className="size-6" />
      </div>
      <div className="max-w-md">
        <h2 className="font-display text-lg font-semibold text-foreground">
          Commencez par le calendrier du semestre
        </h2>
        <p className="mt-1.5 text-sm text-muted-foreground">
          Les cours se posent sur des semaines numérotées (S1, S2…). Indiquez le premier lundi du
          semestre et le nombre de semaines : la grille apparaîtra ensuite, salle par salle.
        </p>
      </div>
      <ol className="flex flex-col gap-2 text-left text-[13px] text-muted-foreground sm:flex-row sm:gap-6">
        <li className="flex items-center gap-2">
          <Etape n={1} /> Définir les semaines
        </li>
        <li className="flex items-center gap-2">
          <Etape n={2} /> Programmer les cours de chaque salle
        </li>
        <li className="flex items-center gap-2">
          <Etape n={3} /> Les étudiants voient leurs séances
        </li>
      </ol>
      <Button className="gap-1.5" onClick={onCalendrier}>
        <CalendarRange className="size-4" />
        Définir le calendrier
      </Button>
    </div>
  );
}

function Etape({ n }: { n: number }) {
  return (
    <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground">
      {n}
    </span>
  );
}
