"use client";

import { useMemo, useRef } from "react";
import { motion } from "motion/react";
import { Lock, CalendarPlus } from "lucide-react";
import { cn } from "@/lib/utils";
import { ajouterJours, formaterYmd, hhmm, minutes, type Ymd } from "@/lib/dates";
import type { Semaine } from "@/hooks/use-catalog";
import type { ModeGrille } from "@/hooks/use-emploi-du-temps";
import type { Seance, Weekday } from "@/types/api";
import { JOURS, couleurCours, seanceFigee } from "./constantes";

const PX_PAR_HEURE = 64;
const HEURE_MIN_DEFAUT = 7;
const HEURE_MAX_DEFAUT = 19;
const LARGEUR_GOUTTIERE = 56;
const LARGEUR_MIN_JOUR = 136;

interface Props {
  semaine: Semaine;
  seances: Seance[];
  mode: ModeGrille;
  /** Jour et minute courants à Douala — null tant que l'API n'a pas répondu. */
  maintenant: { date: Ymd; minute: number } | null;
  onSeance: (seance: Seance) => void;
  onCreneau: (date: Ymd, jour: Weekday, heure: string) => void;
}

interface Bloc {
  seance: Seance;
  debut: number;
  fin: number;
  voie: number;
  voies: number;
}

/**
 * Répartit les séances qui se chevauchent (deux groupes en parallèle, par
 * exemple) sur des « voies » côte à côte plutôt que de les empiler.
 */
function disposer(seances: Seance[]): Bloc[] {
  const tries = [...seances].sort((a, b) => minutes(a.heure_debut) - minutes(b.heure_debut));
  const blocs: Bloc[] = [];
  let groupe: Bloc[] = [];
  let finGroupe = -1;

  const clore = () => {
    for (const b of groupe) b.voies = groupe.length ? Math.max(...groupe.map((g) => g.voie)) + 1 : 1;
    groupe = [];
  };

  for (const s of tries) {
    const debut = minutes(s.heure_debut);
    const fin = minutes(s.heure_fin);
    if (debut >= finGroupe) clore();
    const occupees = new Set(groupe.filter((g) => g.fin > debut).map((g) => g.voie));
    let voie = 0;
    while (occupees.has(voie)) voie++;
    const bloc = { seance: s, debut, fin, voie, voies: 1 };
    groupe.push(bloc);
    blocs.push(bloc);
    finGroupe = Math.max(finGroupe, fin);
  }
  clore();

  return blocs;
}

export function GrilleSemaine({ semaine, seances, mode, maintenant, onSeance, onCreneau }: Props) {
  const { jours, heureMin, heureMax } = useMemo(() => {
    const dimanche = seances.some((s) => s.jour === "DIMANCHE");
    const debuts = seances.map((s) => Math.floor(minutes(s.heure_debut) / 60));
    const fins = seances.map((s) => Math.ceil(minutes(s.heure_fin) / 60));
    return {
      jours: dimanche ? JOURS : JOURS.slice(0, 6),
      heureMin: Math.min(HEURE_MIN_DEFAUT, ...debuts),
      heureMax: Math.max(HEURE_MAX_DEFAUT, ...fins),
    };
  }, [seances]);

  const hauteur = (heureMax - heureMin) * PX_PAR_HEURE;
  const heures = Array.from({ length: heureMax - heureMin + 1 }, (_, i) => heureMin + i);
  const top = (minute: number) => ((minute - heureMin * 60) / 60) * PX_PAR_HEURE;

  const parJour = useMemo(() => {
    const m = new Map<Weekday, Seance[]>();
    for (const s of seances) m.set(s.jour, [...(m.get(s.jour) ?? []), s]);
    return m;
  }, [seances]);

  return (
    <div className="overflow-x-auto rounded-2xl border border-border bg-card shadow-xs">
      <div
        className="grid"
        style={{
          minWidth: LARGEUR_GOUTTIERE + jours.length * LARGEUR_MIN_JOUR,
          gridTemplateColumns: `${LARGEUR_GOUTTIERE}px repeat(${jours.length}, minmax(${LARGEUR_MIN_JOUR}px, 1fr))`,
        }}
      >
        {/* En-têtes */}
        <div className="sticky top-0 z-20 border-b border-border bg-card" />
        {jours.map((j, i) => {
          const date = ajouterJours(semaine.date_debut, i);
          const estAujourdhui = maintenant?.date === date;
          const passe = maintenant ? date < maintenant.date : false;
          return (
            <div
              key={j.valeur}
              className={cn(
                "sticky top-0 z-20 flex flex-col items-center border-b border-l border-border bg-card py-2.5",
                passe && "text-muted-foreground",
              )}
            >
              <span className="text-[11px] font-medium uppercase tracking-wide">{j.court}</span>
              <span
                className={cn(
                  "mt-0.5 flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-sm font-semibold tabular-nums",
                  estAujourdhui ? "bg-primary text-primary-foreground" : "text-foreground",
                  passe && "text-muted-foreground",
                )}
              >
                {formaterYmd(date, { day: "numeric" })}
              </span>
            </div>
          );
        })}

        {/* Gouttière des heures */}
        <div className="relative" style={{ height: hauteur }}>
          {heures.map((h) => (
            <span
              key={h}
              className="absolute right-2 -translate-y-1/2 text-[11px] tabular-nums text-muted-foreground"
              style={{ top: (h - heureMin) * PX_PAR_HEURE }}
            >
              {h === heureMin ? "" : `${String(h).padStart(2, "0")}h`}
            </span>
          ))}
        </div>

        {/* Colonnes */}
        {jours.map((j, i) => {
          const date = ajouterJours(semaine.date_debut, i);
          return (
            <ColonneJour
              key={j.valeur}
              jour={j.valeur}
              date={date}
              hauteur={hauteur}
              heureMin={heureMin}
              top={top}
              blocs={disposer(parJour.get(j.valeur) ?? [])}
              mode={mode}
              maintenant={maintenant}
              onSeance={onSeance}
              onCreneau={onCreneau}
            />
          );
        })}
      </div>
    </div>
  );
}

function ColonneJour({
  jour,
  date,
  hauteur,
  heureMin,
  top,
  blocs,
  mode,
  maintenant,
  onSeance,
  onCreneau,
}: {
  jour: Weekday;
  date: Ymd;
  hauteur: number;
  heureMin: number;
  top: (minute: number) => number;
  blocs: Bloc[];
  mode: ModeGrille;
  maintenant: { date: Ymd; minute: number } | null;
  onSeance: (seance: Seance) => void;
  onCreneau: (date: Ymd, jour: Weekday, heure: string) => void;
}) {
  const ref = useRef<HTMLDivElement>(null);
  const estAujourdhui = maintenant?.date === date;
  const passe = maintenant ? date < maintenant.date : false;

  function clicCreneau(e: React.MouseEvent<HTMLDivElement>) {
    if (!ref.current) return;
    const y = e.clientY - ref.current.getBoundingClientRect().top;
    // Arrondi à la demi-heure : c'est le pas naturel d'un emploi du temps.
    const minute = heureMin * 60 + Math.floor((y / PX_PAR_HEURE) * 2) * 30;
    const h = String(Math.floor(minute / 60)).padStart(2, "0");
    const m = String(minute % 60).padStart(2, "0");
    onCreneau(date, jour, `${h}:${m}`);
  }

  return (
    <div
      ref={ref}
      role="presentation"
      onClick={clicCreneau}
      title={passe ? undefined : "Cliquer pour programmer un cours sur ce créneau"}
      className={cn(
        "group/col relative cursor-pointer border-l border-border",
        passe && "bg-muted/40",
        estAujourdhui && "bg-primary/[0.03]",
      )}
      style={{
        height: hauteur,
        backgroundImage: `repeating-linear-gradient(to bottom, var(--border) 0 1px, transparent 1px ${PX_PAR_HEURE}px)`,
      }}
    >
      <span className="pointer-events-none absolute inset-x-0 top-1/2 hidden -translate-y-1/2 justify-center text-muted-foreground/40 group-hover/col:flex">
        {blocs.length === 0 && !passe && <CalendarPlus className="size-5" />}
      </span>

      {blocs.map((b) => (
        <BlocSeance
          key={b.seance.id}
          bloc={b}
          mode={mode}
          top={top(b.debut)}
          hauteur={Math.max(top(b.fin) - top(b.debut), 24)}
          onClick={() => onSeance(b.seance)}
        />
      ))}

      {estAujourdhui && maintenant && (
        <div
          className="pointer-events-none absolute inset-x-0 z-10 flex items-center"
          style={{ top: top(maintenant.minute) }}
          aria-hidden
        >
          <span className="-ml-1 size-2 rounded-full bg-red-500" />
          <span className="h-px flex-1 bg-red-500" />
        </div>
      )}
    </div>
  );
}

function BlocSeance({
  bloc,
  mode,
  top,
  hauteur,
  onClick,
}: {
  bloc: Bloc;
  mode: ModeGrille;
  top: number;
  hauteur: number;
  onClick: () => void;
}) {
  const s = bloc.seance;
  const compact = hauteur < 56;
  const figee = seanceFigee(s);
  const largeur = 100 / bloc.voies;

  return (
    <motion.button
      type="button"
      initial={{ opacity: 0, scale: 0.97 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.2 }}
      onClick={(e) => {
        e.stopPropagation();
        onClick();
      }}
      aria-label={`${s.matiere ?? "Séance"}, ${hhmm(s.heure_debut)} à ${hhmm(s.heure_fin)}`}
      className={cn(
        "absolute z-[5] flex flex-col overflow-hidden rounded-lg border px-2 py-1.5 text-left shadow-xs transition-shadow hover:z-10 hover:shadow-md focus-visible:z-10 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none",
        couleurCours(s),
        s.is_past && !s.is_active && "opacity-75 saturate-50",
        s.is_active && "ring-2 ring-success/70",
      )}
      style={{
        top: top + 1,
        height: hauteur - 2,
        left: `calc(${bloc.voie * largeur}% + 3px)`,
        width: `calc(${largeur}% - 6px)`,
      }}
    >
      <span className="flex items-start justify-between gap-1">
        <span className={cn("truncate font-semibold", compact ? "text-[11.5px]" : "text-[12.5px]")}>
          {compact && <span className="mr-1 font-normal tabular-nums opacity-80">{hhmm(s.heure_debut)}</span>}
          {s.matiere ?? "Séance"}
        </span>
        {(s.is_active || figee) && (
          <span className="mt-0.5 flex shrink-0 items-center gap-1">
            {s.is_active && (
              <span className="relative flex size-2">
                <span className="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-75" />
                <span className="relative inline-flex size-2 rounded-full bg-success" />
              </span>
            )}
            {figee && <Lock className="size-3 opacity-70" />}
          </span>
        )}
      </span>
      {!compact && (
        <>
          <span className="truncate text-[11.5px] opacity-85">
            {mode === "salle" ? s.enseignant : `${s.salle} · ${s.groupe}`}
          </span>
          <span className="mt-auto truncate text-[11px] tabular-nums opacity-70">
            {hhmm(s.heure_debut)} – {hhmm(s.heure_fin)}
            {s.is_past && !s.is_active && (
              <span className="ml-1.5">{s.etat_final === "present" ? "· tenue" : "· non tenue"}</span>
            )}
          </span>
        </>
      )}
    </motion.button>
  );
}
