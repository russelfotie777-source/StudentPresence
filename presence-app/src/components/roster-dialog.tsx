"use client";

import { useState } from "react";
import { Lock } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { Checkbox } from "@/components/ui/checkbox";
import { GrainOverlay } from "@/components/grain-overlay";
import { cn } from "@/lib/utils";
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
  const progressPct = attendu ? Math.min(100, (checked.size / attendu) * 100) : 0;

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
      <DialogContent className="max-h-[85vh] overflow-y-auto rounded-3xl">
        <DialogHeader>
          <DialogTitle className="font-display text-lg font-bold">
            Confirmer la présence
          </DialogTitle>
        </DialogHeader>

        {/* Effectif — carte hero façon billet */}
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 px-5 py-4 shadow-[0_20px_40px_-14px_rgba(79,70,229,.5)]">
          <GrainOverlay />
          <div className="relative">
            <span className="text-[11.5px] font-bold uppercase tracking-wide text-white/70">
              Effectif déclaré par l&apos;enseignant
            </span>
            <div className="my-1.5 flex items-end gap-2">
              <span className="font-display text-[38px] font-extrabold leading-none tracking-tight text-white">
                {checked.size}
              </span>
              <span className="pb-1 text-[15px] font-semibold text-white/65">
                / {attendu ?? "—"} présents
              </span>
            </div>
            <div className="flex h-1.5 gap-1 overflow-hidden rounded-full bg-white/25">
              <div
                className={cn(
                  "h-full rounded-full transition-all",
                  depasse ? "bg-rose-400" : "bg-white",
                )}
                style={{ width: `${progressPct}%` }}
              />
            </div>
          </div>
        </div>

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
          <div className="flex flex-col gap-1.5">
            {roster.map((etudiant) => {
              const isChecked = checked.has(etudiant.id);
              return (
                <label
                  key={etudiant.id}
                  className={cn(
                    "flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition-colors",
                    isChecked ? "border-indigo-100 bg-indigo-50" : "border-line",
                  )}
                >
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                    {etudiant.name
                      .split(" ")
                      .map((p) => p[0])
                      .slice(0, 2)
                      .join("")
                      .toUpperCase()}
                  </div>
                  <span className="flex-1 truncate font-semibold text-ink-900">
                    {etudiant.name}
                  </span>
                  <Checkbox checked={isChecked} onCheckedChange={() => toggle(etudiant.id)} />
                </label>
              );
            })}
            {roster.length === 0 && (
              <p className="py-4 text-center text-sm text-ink-500">
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
            className="gap-1.5 rounded-xl bg-ink-900 hover:bg-ink-700"
          >
            <Lock className="h-4 w-4" />
            {confirmRoster.isPending
              ? "Envoi…"
              : `Verrouiller et valider · ${checked.size}`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
