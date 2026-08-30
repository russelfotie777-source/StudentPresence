"use client";

import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { useConfirmRoster, useRoster } from "@/hooks/use-seances";
import { ApiError } from "@/lib/api-client";
import type { Seance } from "@/types/api";

export function RosterDialog({
  seance,
  open,
  onOpenChange,
}: {
  seance: Seance;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { data: roster, isLoading } = useRoster(seance.id, open);
  const confirmRoster = useConfirmRoster(seance.id);
  const [checked, setChecked] = useState<Set<number>>(new Set());

  // Initialise `checked` dès que le roster arrive (données async de
  // useRoster) — setState pendant le rendu plutôt que dans un effet, pattern
  // recommandé par React pour "ajuster un state quand une prop change" :
  // https://react.dev/learn/you-might-not-need-an-effect
  const [syncedRoster, setSyncedRoster] = useState(roster);
  if (roster !== syncedRoster) {
    setSyncedRoster(roster);
    if (roster) {
      setChecked(new Set(roster.filter((r) => r.etat === "present").map((r) => r.id)));
    }
  }

  const attendu = seance.push?.etudiants_presents ?? null;
  const depasse = attendu !== null && checked.size > attendu;

  function toggle(id: number) {
    setChecked((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  const apiError = confirmRoster.error instanceof ApiError ? confirmRoster.error.message : null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Confirmer la liste de présence</DialogTitle>
          <DialogDescription>
            {seance.matiere} — {seance.salle}, {seance.heure_debut}
            {attendu !== null && (
              <>
                {" "}
                · effectif déclaré par l&apos;enseignant :{" "}
                <strong className={depasse ? "text-destructive" : ""}>
                  {checked.size} / {attendu}
                </strong>
              </>
            )}
          </DialogDescription>
        </DialogHeader>

        {!seance.push && (
          <Alert variant="destructive">
            <AlertDescription>
              L&apos;enseignant n&apos;a pas encore déclaré son effectif présent — impossible de
              confirmer pour l&apos;instant.
            </AlertDescription>
          </Alert>
        )}

        {isLoading && (
          <div className="flex flex-col gap-2">
            {[...Array(5)].map((_, i) => (
              <Skeleton key={i} className="h-9 w-full" />
            ))}
          </div>
        )}

        {roster && (
          <div className="flex flex-col divide-y">
            {roster.map((etudiant) => (
              <label
                key={etudiant.id}
                className="flex items-center justify-between gap-2 py-2 text-sm"
              >
                <span>{etudiant.name}</span>
                <input
                  type="checkbox"
                  checked={checked.has(etudiant.id)}
                  onChange={() => toggle(etudiant.id)}
                  className="h-4 w-4"
                />
              </label>
            ))}
            {roster.length === 0 && (
              <p className="py-4 text-center text-sm text-muted-foreground">
                Aucun étudiant trouvé pour cette salle/niveau.
              </p>
            )}
          </div>
        )}

        {apiError && (
          <Alert variant="destructive">
            <AlertDescription>{apiError}</AlertDescription>
          </Alert>
        )}

        <DialogFooter>
          <Button
            onClick={() =>
              confirmRoster.mutate(Array.from(checked), {
                onSuccess: () => onOpenChange(false),
              })
            }
            disabled={!seance.push || depasse || confirmRoster.isPending}
          >
            {confirmRoster.isPending ? "Envoi…" : "Verrouiller et valider"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
