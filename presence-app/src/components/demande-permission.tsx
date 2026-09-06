"use client";

import { motion } from "motion/react";
import { MapPin, Camera, Settings, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  estNavigateurIntegre,
  instructionsReactivation,
  type TypePermission,
} from "@/lib/plateforme";
import type { EtatPermission } from "@/hooks/use-permission";

const TEXTES: Record<TypePermission, { titre: string; raison: string; bouton: string }> = {
  position: {
    titre: "Autoriser la position",
    raison:
      "Votre position sert uniquement à vérifier que vous êtes bien près du délégué au moment du pointage. Elle n'est jamais partagée avec les autres étudiants et n'est pas suivie en dehors des séances.",
    bouton: "Autoriser ma position",
  },
  camera: {
    titre: "Autoriser la caméra",
    raison:
      "La caméra sert uniquement à confirmer que c'est bien vous qui vous connectez. L'image ne quitte jamais votre téléphone : seule une empreinte numérique est calculée sur place.",
    bouton: "Autoriser la caméra",
  },
};

/**
 * Écran d'explication affiché AVANT de déclencher le dialogue du navigateur,
 * comme le font les applications natives.
 *
 * La demande système ne peut être présentée qu'une fois : refusée, elle ne se
 * rouvre plus jamais, y compris sur une app native. Expliquer d'abord évite
 * qu'elle soit refusée par réflexe, et c'est la seule protection possible de
 * cette unique tentative.
 */
export function DemandePermission({
  type,
  etat,
  onDemander,
  enCours = false,
}: {
  type: TypePermission;
  etat: EtatPermission;
  onDemander: () => void;
  enCours?: boolean;
}) {
  const textes = TEXTES[type];
  const Icone = type === "position" ? MapPin : Camera;

  if (etat === "refusee") {
    return <PermissionRefusee type={type} />;
  }

  return (
    <div className="flex flex-col items-center gap-3 text-center">
      <div className="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50">
        <Icone className="h-6 w-6 text-indigo-600" />
      </div>
      <h2 className="font-display text-[17px] font-bold text-ink-900">{textes.titre}</h2>
      <p className="max-w-[290px] text-[13.5px] leading-relaxed text-ink-500">{textes.raison}</p>
      <motion.div whileTap={{ scale: 0.96 }} transition={{ type: "spring", stiffness: 500, damping: 22 }}>
        {/* Le déclenchement doit rester dans le gestionnaire du clic : sorti du
            geste de l'utilisateur, iOS peut ignorer la demande sans rien
            afficher. */}
        <Button onClick={onDemander} disabled={enCours} className="h-11 rounded-xl px-5">
          {enCours ? "Demande en cours…" : textes.bouton}
        </Button>
      </motion.div>
    </div>
  );
}

/**
 * Chemin de secours après un refus. Le dialogue ne pouvant plus être rouvert,
 * ces instructions sont la seule issue réelle — d'où leur précision par
 * plateforme plutôt qu'un « vérifiez vos réglages » inutilisable.
 */
export function PermissionRefusee({ type }: { type: TypePermission }) {
  const etapes = instructionsReactivation(type);
  const libelle = type === "position" ? "position" : "caméra";

  if (estNavigateurIntegre()) {
    return (
      <div className="flex w-full flex-col items-center gap-2.5 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50">
          <ExternalLink className="h-5 w-5 text-amber-600" />
        </div>
        <h2 className="font-display text-[16px] font-bold text-ink-900">
          Ouvrez la page dans votre navigateur
        </h2>
        <p className="max-w-[290px] text-[13px] leading-relaxed text-ink-500">
          Vous consultez cette page depuis une autre application, qui bloque l&apos;accès à la{" "}
          {libelle}. Touchez le menu « ⋯ » puis « Ouvrir dans le navigateur » et reconnectez-vous.
        </p>
      </div>
    );
  }

  return (
    <div className="flex w-full flex-col gap-3">
      <div className="flex items-center gap-2.5">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-50">
          <Settings className="h-5 w-5 text-amber-600" />
        </div>
        <div className="text-left">
          <p className="text-sm font-semibold text-ink-900">Accès à la {libelle} refusé</p>
          <p className="text-xs text-ink-500">
            Votre navigateur ne peut plus le redemander, il faut le réactiver à la main.
          </p>
        </div>
      </div>

      <ol className="flex flex-col gap-2 rounded-2xl border border-line bg-card p-3.5">
        {etapes.map((etape, i) => (
          <li key={etape} className="flex gap-2.5 text-[13px] leading-relaxed text-ink-500">
            <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[11px] font-bold text-secondary-foreground">
              {i + 1}
            </span>
            <span>{etape}</span>
          </li>
        ))}
      </ol>
    </div>
  );
}
