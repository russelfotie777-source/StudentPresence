"use client";

import { useState } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { motion } from "motion/react";
import { CalendarX2, CheckCircle2, XCircle, Layers, RotateCcw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { apiFetch } from "@/lib/api-client";
import { salleHooks } from "@/hooks/use-catalog";
import type { Seance } from "@/types/api";
import { cn } from "@/lib/utils";
import { libelleSalle } from "@/lib/catalogue";

interface PageHistorique {
  data: Seance[];
  meta: { current_page: number; last_page: number; total: number };
  stats: { total: number; present: number; absent: number };
}

/** Valeur du choix « toutes les salles » — un Select ne peut pas porter une option vide. */
const TOUTES = "toutes";

export default function HistoriquePage() {
  const [salleId, setSalleId] = useState(TOUTES);
  const { data: salles } = salleHooks.useList();

  const requete = useInfiniteQuery({
    queryKey: ["historique-seances", salleId],
    queryFn: ({ pageParam }) =>
      apiFetch<PageHistorique>(
        `/api/historique-seances?page=${pageParam}${salleId !== TOUTES ? `&salle_id=${salleId}` : ""}`,
      ),
    initialPageParam: 1,
    getNextPageParam: (derniere) =>
      derniere.meta.current_page < derniere.meta.last_page
        ? derniere.meta.current_page + 1
        : undefined,
  });

  const seances = requete.data?.pages.flatMap((p) => p.data) ?? [];
  const stats = requete.data?.pages[0]?.stats;
  const salleActive = salles?.find((s) => String(s.id) === salleId);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
            Historique des séances
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Toutes les séances passées et leur état final, salle par salle.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Select value={salleId} onValueChange={(v) => setSalleId(v ?? TOUTES)}>
            <SelectTrigger className="h-10 w-full rounded-xl sm:w-72">
              {/* Sans fonction de rendu, le déclencheur affiche l'id brut de la
                  salle sélectionnée au lieu de son nom — comportement par
                  défaut du composant, pas un choix. */}
              <SelectValue placeholder="Toutes les salles">
                {() => (salleId === TOUTES ? "Toutes les salles" : salleActive && libelleSalle(salleActive))}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {/* Sans cette entrée, un filtre posé ne pouvait plus être retiré :
                  le texte « Toutes les salles » n'était qu'un libellé de repli. */}
              <SelectItem value={TOUTES}>Toutes les salles</SelectItem>
              {salles?.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {libelleSalle(s)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {salleId !== TOUTES && (
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setSalleId(TOUTES)}
              aria-label="Retirer le filtre"
            >
              <RotateCcw className="size-4" />
            </Button>
          )}
        </div>
      </div>

      {stats && (
        <div className="grid grid-cols-3 gap-3">
          <Statistique
            icon={Layers}
            valeur={stats.total}
            label={salleActive ? `séances · ${salleActive.nom}` : "séances au total"}
          />
          <Statistique icon={CheckCircle2} valeur={stats.present} label="honorées" ton="success" />
          <Statistique icon={XCircle} valeur={stats.absent} label="non honorées" ton="destructive" />
        </div>
      )}

      {requete.isLoading && (
        <div className="flex flex-col gap-2">
          {[...Array(5)].map((_, i) => (
            <Skeleton key={i} className="h-[68px] rounded-xl" />
          ))}
        </div>
      )}

      {!requete.isLoading && seances.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-14 text-center">
          <CalendarX2 className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">
            Aucune séance {salleActive ? `pour ${salleActive.nom}` : "enregistrée"}.
          </p>
        </div>
      )}

      <div className="flex flex-col gap-2">
        {seances.map((s) => (
          <LigneSeance key={s.id} seance={s} />
        ))}
      </div>

      {requete.hasNextPage && (
        <div className="flex flex-col items-center gap-2 pt-1">
          <Button
            variant="outline"
            className="h-10 rounded-xl px-5"
            onClick={() => requete.fetchNextPage()}
            disabled={requete.isFetchingNextPage}
          >
            {requete.isFetchingNextPage ? "Chargement…" : "Voir plus"}
          </Button>
          <span className="text-xs text-muted-foreground tabular-nums">
            {seances.length} sur {stats?.total ?? 0}
          </span>
        </div>
      )}
    </div>
  );
}

function Statistique({
  icon: Icon,
  valeur,
  label,
  ton,
}: {
  icon: typeof Layers;
  valeur: number;
  label: string;
  ton?: "success" | "destructive";
}) {
  return (
    <div className="flex flex-col gap-1 rounded-2xl border border-border bg-card p-4 shadow-xs">
      <Icon
        className={cn(
          "size-4",
          ton === "success"
            ? "text-success"
            : ton === "destructive"
              ? "text-destructive"
              : "text-muted-foreground",
        )}
      />
      <p className="font-display text-2xl leading-none font-semibold text-foreground tabular-nums">
        {valeur}
      </p>
      <p className="truncate text-xs text-muted-foreground">{label}</p>
    </div>
  );
}

function LigneSeance({ seance: s }: { seance: Seance }) {
  const present = s.etat_final === "present";

  return (
    <motion.div
      initial={{ opacity: 0, y: 6 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
      className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-border bg-card p-3.5 shadow-xs"
    >
      <div className="flex w-[68px] shrink-0 flex-col items-center rounded-lg bg-muted py-1.5 text-center">
        <span className="text-[13px] leading-none font-semibold text-foreground tabular-nums">
          {s.heure_debut.slice(0, 5)}
        </span>
        <span className="mt-0.5 text-[10px] text-muted-foreground tabular-nums">
          {s.heure_fin.slice(0, 5)}
        </span>
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-foreground">{s.matiere ?? "Séance"}</p>
        <p className="truncate text-xs text-muted-foreground">
          {s.salle} · {s.enseignant}
        </p>
      </div>

      <span className="shrink-0 text-xs text-muted-foreground">{dateLisible(s.date_seance)}</span>

      <span
        className={cn(
          "shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium",
          present ? "bg-success/15 text-success" : "bg-destructive/10 text-destructive",
        )}
      >
        {present ? "Honorée" : "Non honorée"}
      </span>
    </motion.div>
  );
}


function dateLisible(date: string | null): string {
  if (!date) {
    return "—";
  }

  return new Date(`${date}T00:00:00`).toLocaleDateString("fr-FR", {
    weekday: "short",
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}
