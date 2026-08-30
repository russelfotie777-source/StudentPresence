"use client";

import { useEffect } from "react";
import { CheckCircle2, Loader2, MapPin } from "lucide-react";
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
import { useGeolocation } from "@/hooks/use-geolocation";
import { useCheckIn } from "@/hooks/use-seances";
import { ApiError } from "@/lib/api-client";
import type { Seance } from "@/types/api";

export function CheckInDialog({
  seance,
  open,
  onOpenChange,
}: {
  seance: Seance;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const geo = useGeolocation();
  const checkIn = useCheckIn(seance.id);

  useEffect(() => {
    if (open) geo.locate();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function handleConfirm() {
    if (!geo.coords) return;
    checkIn.mutate(geo.coords, { onSuccess: () => onOpenChange(false) });
  }

  const apiError = checkIn.error instanceof ApiError ? checkIn.error.message : null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Confirmer ma présence</DialogTitle>
          <DialogDescription>
            {seance.matiere} — {seance.salle}, {seance.heure_debut}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col items-center gap-3 py-4 text-center">
          {geo.status === "loading" && (
            <>
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
              </div>
              <p className="text-sm text-muted-foreground">Localisation en cours…</p>
            </>
          )}

          {geo.status === "success" && !checkIn.isSuccess && (
            <>
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                <MapPin className="h-6 w-6 text-primary" />
              </div>
              <p className="text-sm text-muted-foreground">
                Position obtenue. Confirmez pour valider votre présence — la distance avec le
                délégué est vérifiée côté serveur.
              </p>
            </>
          )}

          {checkIn.isSuccess && (
            <>
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-success/15">
                <CheckCircle2 className="h-6 w-6 text-success" />
              </div>
              <p className="text-sm font-medium text-foreground">
                Présence confirmée ({checkIn.data?.distance}m du délégué)
              </p>
            </>
          )}

          {geo.status === "error" && (
            <Alert variant="destructive" className="text-left">
              <AlertDescription>{geo.errorMessage}</AlertDescription>
            </Alert>
          )}

          {apiError && (
            <Alert variant="destructive" className="text-left">
              <AlertDescription>{apiError}</AlertDescription>
            </Alert>
          )}
        </div>

        <DialogFooter className="gap-2">
          {geo.status === "error" && (
            <Button variant="outline" onClick={() => geo.locate()}>
              Réessayer
            </Button>
          )}
          <Button
            onClick={handleConfirm}
            disabled={!geo.coords || checkIn.isPending || checkIn.isSuccess}
            className="rounded-lg"
          >
            {checkIn.isPending ? "Envoi…" : "Confirmer ma présence"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
