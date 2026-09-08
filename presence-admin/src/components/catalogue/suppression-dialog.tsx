"use client";

import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export interface Consequence {
  libelle: string;
  nombre: number;
}

/**
 * Confirmation avant suppression dans le catalogue.
 *
 * Supprimer un niveau emporte ses filières, qui emportent leurs salles, qui
 * emportent leurs modèles de cours — le tout sans retour possible. L'écran
 * déclenchait cette cascade au premier clic, sans un mot. Ce dialogue énonce
 * ce qui disparaîtra réellement, en s'appuyant sur les données déjà chargées
 * plutôt que sur une formule vague.
 */
export function SuppressionDialog({
  ouvert,
  onOuvert,
  nom,
  type,
  consequences,
  enCours,
  onConfirmer,
}: {
  ouvert: boolean;
  onOuvert: (o: boolean) => void;
  nom: string;
  type: string;
  consequences: Consequence[];
  enCours: boolean;
  onConfirmer: () => void;
}) {
  const emportes = consequences.filter((c) => c.nombre > 0);

  return (
    <Dialog open={ouvert} onOpenChange={onOuvert}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Supprimer {type} « {nom} » ?</DialogTitle>
          <DialogDescription>
            {emportes.length === 0
              ? "Cette suppression est définitive."
              : "Cette suppression est définitive et emporte aussi ce qui en dépend."}
          </DialogDescription>
        </DialogHeader>

        {emportes.length > 0 && (
          <div className="flex items-start gap-2.5 rounded-xl border border-destructive/25 bg-destructive/10 px-3.5 py-3">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-destructive" />
            <div className="flex flex-col gap-1 text-[13px] text-destructive">
              {emportes.map((c) => (
                <span key={c.libelle}>
                  <span className="font-semibold tabular-nums">{c.nombre}</span> {c.libelle}
                </span>
              ))}
            </div>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => onOuvert(false)}>
            Annuler
          </Button>
          <Button variant="destructive" disabled={enCours} onClick={onConfirmer}>
            {enCours ? "Suppression…" : "Supprimer définitivement"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
