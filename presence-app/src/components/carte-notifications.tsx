"use client";

import { motion } from "motion/react";
import { Bell, ShieldOff, ShieldBan, ShieldCheck, Check } from "lucide-react";
import { useMarquerLue, useNotifications } from "@/hooks/use-notifications";
import type { Notification } from "@/types/api";
import { cn } from "@/lib/utils";

const ICONES = {
  restreint: { icon: ShieldOff, classe: "bg-warning/20 text-warning-foreground" },
  bloque: { icon: ShieldBan, classe: "bg-destructive/10 text-destructive" },
  actif: { icon: ShieldCheck, classe: "bg-success/15 text-success" },
} as const;

function dateLisible(iso: string) {
  return new Date(iso).toLocaleDateString("fr-FR", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
}

/**
 * Notifications reçues, les non lues en tête et mises en avant. Une
 * restriction de compte arrive ici : c'est la trace qui reste après que
 * la bannière d'accueil a disparu.
 */
export function CarteNotifications() {
  const { data } = useNotifications();
  const marquerLue = useMarquerLue();

  if (!data || data.notifications.length === 0) return null;

  return (
    <div className="flex flex-col gap-2">
      <h2 className="flex items-center gap-2 px-1 text-[12.5px] font-semibold uppercase tracking-wide text-ink-300">
        <Bell className="size-3.5" />
        Notifications
        {data.non_lues > 0 && (
          <span className="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground tabular-nums">
            {data.non_lues}
          </span>
        )}
      </h2>
      <motion.div
        initial="hidden"
        animate="show"
        variants={{ show: { transition: { staggerChildren: 0.05 } } }}
        className="flex flex-col gap-2"
      >
        {data.notifications.map((n) => (
          <LigneNotification
            key={n.id}
            notification={n}
            onLue={() => marquerLue.mutate(n.id)}
          />
        ))}
      </motion.div>
    </div>
  );
}

function LigneNotification({ notification: n, onLue }: { notification: Notification; onLue: () => void }) {
  const ton = (n.statut && ICONES[n.statut]) || { icon: Bell, classe: "bg-secondary text-secondary-foreground" };
  const Icone = ton.icon;

  return (
    <motion.div
      variants={{
        hidden: { opacity: 0, y: 8 },
        show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] } },
      }}
      className={cn(
        "flex items-start gap-3 rounded-2xl border p-3.5",
        n.lue ? "border-line bg-card" : "border-primary/30 bg-primary/5",
      )}
    >
      <div className={cn("flex size-9 shrink-0 items-center justify-center rounded-xl", ton.classe)}>
        <Icone className="size-[18px]" />
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-start justify-between gap-2">
          <p className={cn("text-sm text-ink-900", !n.lue && "font-semibold")}>{n.titre}</p>
          <span className="shrink-0 text-[11px] text-ink-300">{dateLisible(n.date)}</span>
        </div>
        <p className="mt-0.5 text-[13px] leading-relaxed text-ink-500">{n.message}</p>
        {n.motif && (
          <p className="mt-1.5 rounded-lg bg-muted/60 px-2.5 py-1.5 text-[12.5px] text-ink-900">
            Motif&nbsp;: {n.motif}
          </p>
        )}
        {!n.lue && (
          <button
            type="button"
            onClick={onLue}
            className="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-primary"
          >
            <Check className="size-3.5" />
            Marquer comme lue
          </button>
        )}
      </div>
    </motion.div>
  );
}
