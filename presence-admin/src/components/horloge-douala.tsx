"use client";

import { Clock } from "lucide-react";
import type { useHeureDouala } from "@/hooks/use-heure";
import { FUSEAU } from "@/lib/dates";

/**
 * L'heure qui fait foi : celle de Douala, servie par l'API. Rendue pour
 * qu'un admin dont la machine est réglée sur un autre fuseau voie tout de
 * suite sur quelle horloge l'écran s'aligne.
 */
export function HorlogeDouala({ heure }: { heure: ReturnType<typeof useHeureDouala> }) {
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
