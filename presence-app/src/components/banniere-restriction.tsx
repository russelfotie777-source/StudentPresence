"use client";

import { motion } from "motion/react";
import { ShieldOff } from "lucide-react";
import type { User } from "@/types/api";

/**
 * Visible tant que le compte est restreint, sur l'écran d'accueil : la
 * personne doit comprendre pourquoi son pointage est refusé sans avoir à
 * essayer, et sans avoir à ouvrir ses notifications.
 */
export function BanniereRestriction({ user }: { user: User }) {
  if (user.statut_compte !== "restreint") return null;

  return (
    <motion.div
      initial={{ opacity: 0, y: -6 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      role="status"
      className="flex items-start gap-3 rounded-2xl border border-warning/50 bg-warning/15 px-4 py-3.5"
    >
      <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-warning/25">
        <ShieldOff className="size-[18px] text-warning-foreground" />
      </div>
      <div className="min-w-0 text-[13.5px] leading-relaxed">
        <p className="font-semibold text-ink-900">Votre compte est restreint</p>
        <p className="mt-0.5 text-ink-500">
          Le pointage de présence vous est refusé jusqu&apos;à nouvel ordre de
          l&apos;administration.
          {user.motif_statut && (
            <>
              {" "}
              Motif&nbsp;: <span className="font-medium text-ink-900">{user.motif_statut}</span>
            </>
          )}
        </p>
      </div>
    </motion.div>
  );
}
