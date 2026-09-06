"use client";

import { motion } from "motion/react";
import { Button } from "@/components/ui/button";

/**
 * Bouton de chargement incrémental des listes paginées. Affiche toujours où
 * on en est ("20 sur 57") : sur mobile, une liste tronquée sans repère laisse
 * croire qu'on a tout vu.
 */
export function VoirPlus({
  affiches,
  total,
  onClick,
  isLoading,
  hasNextPage,
}: {
  affiches: number;
  total: number;
  onClick: () => void;
  isLoading: boolean;
  hasNextPage: boolean;
}) {
  // hasNextPage fait foi (il vient de la pagination serveur) plutôt qu'une
  // comparaison de compteurs, qui se décale dès qu'une ligne est ajoutée ou
  // supprimée entre deux pages.
  if (!hasNextPage) return null;

  return (
    <div className="flex flex-col items-center gap-2 pt-1">
      <motion.div whileTap={{ scale: 0.96 }} transition={{ type: "spring", stiffness: 500, damping: 22 }}>
        <Button variant="outline" className="h-10 rounded-xl px-5" onClick={onClick} disabled={isLoading}>
          {isLoading ? "Chargement…" : "Voir plus"}
        </Button>
      </motion.div>
      <span className="text-xs text-ink-300">
        {affiches} sur {total}
      </span>
    </div>
  );
}
