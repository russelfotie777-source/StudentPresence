"use client";

import { useState } from "react";
import { MapPin, Camera, Check, X, HelpCircle, ChevronDown } from "lucide-react";
import { usePermission, type EtatPermission } from "@/hooks/use-permission";
import { PermissionRefusee } from "@/components/demande-permission";
import { cn } from "@/lib/utils";
import type { TypePermission } from "@/lib/plateforme";

const LIBELLES: Record<EtatPermission, { texte: string; classe: string }> = {
  accordee: { texte: "Autorisée", classe: "bg-success/15 text-success" },
  refusee: { texte: "Refusée", classe: "bg-destructive/10 text-destructive" },
  a_demander: { texte: "À autoriser", classe: "bg-warning/20 text-warning-foreground" },
  inconnu: { texte: "Inconnue", classe: "bg-secondary text-secondary-foreground" },
};

/**
 * Récapitulatif des autorisations, à la manière de l'écran de réglages d'une
 * application mobile : savoir d'un coup d'œil ce qui est accordé, et comment
 * revenir en arrière.
 *
 * L'état se met à jour tout seul quand l'utilisateur autorise depuis les
 * réglages du navigateur et revient sur l'app (voir usePermission).
 */
export function CarteAutorisations({ afficherCamera }: { afficherCamera: boolean }) {
  return (
    <div className="flex flex-col gap-2">
      <h2 className="px-1 text-[12.5px] font-semibold uppercase tracking-wide text-ink-300">
        Autorisations
      </h2>
      <div className="overflow-hidden rounded-2xl border border-line bg-card">
        <LigneAutorisation
          type="position"
          libelle="Position"
          usage="Vérifier votre présence près du délégué"
        />
        {afficherCamera && (
          <LigneAutorisation
            type="camera"
            libelle="Caméra"
            usage="Confirmer votre identité à la connexion"
          />
        )}
      </div>
    </div>
  );
}

function LigneAutorisation({
  type,
  libelle,
  usage,
}: {
  type: TypePermission;
  libelle: string;
  usage: string;
}) {
  const { etat } = usePermission(type);
  const [ouvert, setOuvert] = useState(false);
  const Icone = type === "position" ? MapPin : Camera;
  const badge = LIBELLES[etat];
  const Statut = etat === "accordee" ? Check : etat === "refusee" ? X : HelpCircle;

  return (
    <div className="border-b border-line last:border-b-0">
      <button
        type="button"
        onClick={() => etat === "refusee" && setOuvert((o) => !o)}
        className="flex w-full items-center gap-3 px-4 py-3.5 text-left"
        // Seul l'état "refusée" a quelque chose à déplier : des instructions.
        aria-expanded={etat === "refusee" ? ouvert : undefined}
      >
        <Icone className="h-[18px] w-[18px] shrink-0 text-ink-300" />
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-ink-900">{libelle}</p>
          <p className="truncate text-xs text-ink-300">{usage}</p>
        </div>
        <span
          className={cn(
            "flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium",
            badge.classe,
          )}
        >
          <Statut className="h-3 w-3" />
          {badge.texte}
        </span>
        {etat === "refusee" && (
          <ChevronDown
            className={cn("h-4 w-4 shrink-0 text-ink-300 transition-transform", ouvert && "rotate-180")}
          />
        )}
      </button>

      {etat === "refusee" && ouvert && (
        <div className="px-4 pb-4">
          <PermissionRefusee type={type} />
        </div>
      )}
    </div>
  );
}
