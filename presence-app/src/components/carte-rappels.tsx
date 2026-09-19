"use client";

import { BellRing, BellOff, Check, Loader2, Smartphone } from "lucide-react";
import { usePush, type EtatPush } from "@/hooks/use-push";
import { plateforme } from "@/lib/plateforme";
import { cn } from "@/lib/utils";

const BADGES: Record<EtatPush, { texte: string; classe: string }> = {
  actif: { texte: "Activés", classe: "bg-success/15 text-success" },
  inactif: { texte: "Désactivés", classe: "bg-secondary text-secondary-foreground" },
  refuse: { texte: "Bloqués", classe: "bg-destructive/10 text-destructive" },
  indisponible: { texte: "Indisponibles ici", classe: "bg-secondary text-secondary-foreground" },
  inconnu: { texte: "…", classe: "bg-secondary text-secondary-foreground" },
};

/**
 * Rappels de pointage sur cet appareil : l'ouverture du pointage, puis une
 * dernière chance avant la fermeture (et, pour un délégué, l'envoi de la
 * position). Un bouton, un état lisible, et la marche à suivre quand le
 * téléphone ne peut pas en recevoir.
 */
export function CarteRappels({ delegue }: { delegue: boolean }) {
  const { etat, enCours, erreur, activer, desactiver } = usePush();
  const badge = BADGES[etat];
  const ios = plateforme() === "ios";
  const installee = typeof window !== "undefined" && window.matchMedia("(display-mode: standalone)").matches;

  return (
    <div className="flex flex-col gap-2">
      <h2 className="px-1 text-xs font-semibold text-ink-300">
        Rappels de pointage
      </h2>
      <div className="rounded-2xl border border-line bg-card px-4 py-3.5">
        <div className="flex items-center gap-3">
          {etat === "actif" ? (
            <BellRing className="h-[18px] w-[18px] shrink-0 text-success" />
          ) : (
            <BellOff className="h-[18px] w-[18px] shrink-0 text-ink-300" />
          )}
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium text-ink-900">Notifications sur ce téléphone</p>
            <p className="text-xs text-ink-300">
              {delegue
                ? "Envoyer la position avant le cours, puis suivre le pointage"
                : "Quand le pointage ouvre, et avant qu'il ferme"}
            </p>
          </div>
          <span
            className={cn(
              "flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium",
              badge.classe,
            )}
          >
            {etat === "actif" && <Check className="h-3 w-3" />}
            {badge.texte}
          </span>
        </div>

        {(etat === "inactif" || etat === "actif") && (
          <button
            type="button"
            disabled={enCours}
            onClick={() => (etat === "actif" ? desactiver() : activer())}
            className={cn(
              "mt-3 flex h-10 w-full items-center justify-center gap-2 rounded-xl text-sm font-medium transition-colors disabled:opacity-60",
              etat === "actif"
                ? "border border-line bg-transparent text-ink-700"
                : "bg-primary text-primary-foreground",
            )}
          >
            {enCours && <Loader2 className="h-4 w-4 animate-spin" />}
            {etat === "actif" ? "Ne plus recevoir de rappels ici" : "Activer les rappels"}
          </button>
        )}

        {etat === "refuse" && (
          <p className="mt-3 rounded-xl bg-muted/60 px-3 py-2.5 text-xs leading-relaxed text-ink-500">
            Les notifications ont été bloquées pour ce site. Réautorisez-les dans les réglages du
            navigateur (icône de cadenas ou ⓘ à côté de l&apos;adresse → Notifications), puis
            revenez ici.
          </p>
        )}

        {etat === "indisponible" && (
          <p className="mt-3 flex items-start gap-2 rounded-xl bg-muted/60 px-3 py-2.5 text-xs leading-relaxed text-ink-500">
            <Smartphone className="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              {ios && !installee
                ? "Sur iPhone, les rappels ne fonctionnent qu'une fois l'app installée : Partager → « Sur l'écran d'accueil », puis ouvrez-la depuis l'icône."
                : "Ce navigateur ne peut pas recevoir de notifications. Les rappels restent visibles dans la cloche de l'app."}
            </span>
          </p>
        )}

        {erreur && <p className="mt-2 text-xs text-destructive">{erreur}</p>}
      </div>
    </div>
  );
}
