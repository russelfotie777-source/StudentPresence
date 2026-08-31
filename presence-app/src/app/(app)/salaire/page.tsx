"use client";

import { motion, type Variants } from "motion/react";
import { AlertTriangle, TrendingDown, Wallet } from "lucide-react";
import Link from "next/link";
import { Skeleton } from "@/components/ui/skeleton";
import { GrainOverlay } from "@/components/grain-overlay";
import { AnimatedNumber } from "@/components/animated-number";
import { useMyPayroll } from "@/hooks/use-payroll";

function formatFcfa(n: number) {
  return `${Math.round(n).toLocaleString("fr-FR")} FCFA`;
}

const listVariants: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.055, delayChildren: 0.15 } },
};
const itemVariants: Variants = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: [0.22, 1, 0.36, 1] } },
};

export default function SalairePage() {
  const { data, isLoading } = useMyPayroll();

  return (
    <div className="flex flex-col gap-6">
      <motion.h1
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: "easeOut" }}
        className="font-display text-[26px] font-bold tracking-tight text-ink-900"
      >
        Mon salaire
      </motion.h1>

      {isLoading && (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-[152px] w-full rounded-2xl" />
          <Skeleton className="h-24 w-full rounded-2xl" />
          <Skeleton className="h-24 w-full rounded-2xl" />
        </div>
      )}

      {data && (
        <>
          <motion.div
            initial={{ opacity: 0, y: 16, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            className="relative overflow-hidden rounded-[26px] bg-gradient-to-br from-indigo-500 to-indigo-600 p-5 shadow-[0_24px_48px_-16px_rgba(79,70,229,.55)]"
          >
            <GrainOverlay className="rounded-[26px]" />
            <div
              aria-hidden
              className="pointer-events-none absolute -right-14 -top-14 h-44 w-44 rounded-full opacity-50 blur-3xl"
              style={{ background: "radial-gradient(circle, rgba(255,255,255,.55) 0%, transparent 70%)" }}
            />

            <div className="relative flex items-center gap-2 text-white/80">
              <Wallet className="h-4 w-4" />
              <span className="text-[11px] font-bold uppercase tracking-wider">Total gagné</span>
            </div>
            <p className="relative mt-2 font-display text-[34px] font-extrabold leading-none text-white">
              <AnimatedNumber value={data.total_salaire} formatter={formatFcfa} />
            </p>

            <div className="relative mt-4 flex items-center gap-2 border-t border-white/20 pt-3.5 text-white/85">
              <TrendingDown className="h-3.5 w-3.5" />
              <span className="text-xs">
                Dont{" "}
                <span className="font-semibold text-white">
                  <AnimatedNumber value={data.total_penalite_retard} formatter={formatFcfa} />
                </span>{" "}
                de pénalités de retard
              </span>
            </div>
          </motion.div>

          <motion.div variants={listVariants} initial="hidden" animate="show" className="flex flex-col gap-2.5">
            {data.lignes.map((ligne) => (
              <motion.div
                key={ligne.seance_id}
                variants={itemVariants}
                className="flex items-center justify-between gap-3 rounded-2xl border border-line bg-card p-4"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-ink-900">{ligne.matiere ?? "—"}</p>
                  <p className="truncate text-xs text-ink-500">
                    {ligne.date} · {ligne.salle} · {ligne.heure_debut.slice(0, 5)}
                  </p>
                  {ligne.retard_minutes > 0 && (
                    <span className="mt-1.5 inline-flex items-center gap-1 rounded-full bg-warning/20 px-2 py-0.5 text-[11px] font-medium text-warning-foreground">
                      <AlertTriangle className="h-3 w-3" />
                      {ligne.retard_minutes} min de retard
                    </span>
                  )}
                </div>
                <div className="shrink-0 text-right">
                  <p className="font-display font-bold text-ink-900">{formatFcfa(ligne.salaire)}</p>
                  {ligne.penalite_retard > 0 && (
                    <>
                      <p className="text-xs text-destructive">-{formatFcfa(ligne.penalite_retard)}</p>
                      <Link
                        href={`/requetes?seance_id=${ligne.seance_id}`}
                        className="text-xs font-medium text-primary"
                      >
                        Contester
                      </Link>
                    </>
                  )}
                </div>
              </motion.div>
            ))}
            {data.lignes.length === 0 && (
              <p className="mt-8 text-center text-sm text-ink-500">
                Aucune séance rémunérée pour l&apos;instant.
              </p>
            )}
          </motion.div>
        </>
      )}
    </div>
  );
}
