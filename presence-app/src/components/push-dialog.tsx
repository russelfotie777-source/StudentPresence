"use client";

import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { usePush } from "@/hooks/use-seances";
import type { Seance } from "@/types/api";

export function PushDialog({
  seance,
  open,
  onOpenChange,
}: {
  seance: Seance;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const push = usePush(seance.id);
  const [count, setCount] = useState(seance.push?.etudiants_presents ?? 0);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Déclarer l&apos;effectif présent</DialogTitle>
        </DialogHeader>

        <div className="flex flex-col gap-2 py-2">
          <Label htmlFor="count">Nombre d&apos;étudiants présents dans la salle</Label>
          <Input
            id="count"
            type="number"
            min={0}
            value={count}
            onChange={(e) => setCount(Number(e.target.value))}
            className="h-12 rounded-xl text-center text-lg font-semibold"
          />
        </div>

        <DialogFooter>
          <Button
            onClick={() => push.mutate(count, { onSuccess: () => onOpenChange(false) })}
            disabled={push.isPending}
            className="rounded-lg"
          >
            {push.isPending ? "Envoi…" : "Valider"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
