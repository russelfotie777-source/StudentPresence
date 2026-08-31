"use client";

import { motion, type Variants } from "motion/react";
import { Skeleton } from "@/components/ui/skeleton";
import { SpaceEmptyState } from "@/components/space-empty-state";
import { cn } from "@/lib/utils";
import { useHistorySeances } from "@/hooks/use-seances";
import type { Seance } from "@/types/api";

const listVariants: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.04 } },
};
const itemVariants: Variants = {
  hidden: { opacity: 0, y: 10 },
  show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] } },
};

function formatDateHeading(dateSeance: string | null) {
  if (!dateSeance) return "Date inconnue";
  const d = new Date(`${dateSeance}T00:00:00`);
  const label = d.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
  return label.charAt(0).toUpperCase() + label.slice(1);
}

function groupByDate(seances: Seance[]) {
  const groups: { date: string | null; items: Seance[] }[] = [];
  for (const seance of seances) {
    const last = groups[groups.length - 1];
    if (last && last.date === seance.date_seance) {
      last.items.push(seance);
    } else {
      groups.push({ date: seance.date_seance, items: [seance] });
    }
  }
  return groups;
}

export default function HistoriquePage() {
  const { data: seances, isLoading } = useHistorySeances();
  const groups = groupByDate(seances ?? []);

  return (
    <div className="flex flex-col gap-6">
      <motion.h1
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: "easeOut" }}
        className="font-display text-[26px] font-bold tracking-tight text-ink-900"
      >
        Historique
      </motion.h1>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} className="h-20 w-full rounded-2xl" />
          ))}
        </div>
      )}

      {!isLoading && seances?.length === 0 && (
        <SpaceEmptyState
          title="Aucun historique pour l'instant"
          subtitle="Vos séances passées apparaîtront ici."
        />
      )}

      {groups.map((group) => (
        <div key={group.date ?? "sans-date"} className="flex flex-col gap-2.5">
          <p className="px-1 text-[12.5px] font-semibold uppercase tracking-wide text-ink-300">
            {formatDateHeading(group.date)}
          </p>
          <motion.div
            variants={listVariants}
            initial="hidden"
            animate="show"
            className="flex flex-col gap-2.5"
          >
            {group.items.map((seance) => (
              <motion.div key={seance.id} variants={itemVariants}>
                <HistoriqueRow seance={seance} />
              </motion.div>
            ))}
          </motion.div>
        </div>
      ))}
    </div>
  );
}

function HistoriqueRow({ seance }: { seance: Seance }) {
  const statut = seance.ma_presence ?? seance.etat_final;
  const present = statut === "present";

  return (
    <div className="surface-card flex items-center gap-3 rounded-2xl border border-line p-4">
      <div className="flex w-[52px] shrink-0 flex-col items-center rounded-xl bg-surface-2 py-2 text-center">
        <span className="font-display text-[15px] font-bold leading-none text-ink-900">
          {seance.heure_debut.slice(0, 5)}
        </span>
        <span className="mt-1 text-[10px] text-ink-300">{seance.heure_fin.slice(0, 5)}</span>
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate font-semibold text-ink-900">{seance.matiere ?? "Séance"}</p>
        <p className="truncate text-sm text-ink-500">{seance.salle}</p>
        <p className="truncate text-xs text-ink-300">{seance.enseignant}</p>
      </div>

      <span
        className={cn(
          "shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold",
          present ? "bg-emerald-100 text-emerald-500" : "bg-rose-100 text-rose-500",
        )}
      >
        {present ? "Présent" : "Absent"}
      </span>
    </div>
  );
}
