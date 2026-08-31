"use client";

import { useEffect } from "react";
import { CheckCircle2, MapPin } from "lucide-react";
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
import { cn } from "@/lib/utils";
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
      <DialogContent className="rounded-3xl">
        <DialogHeader>
          <DialogTitle className="font-display text-lg font-bold">
            Confirmer ma présence
          </DialogTitle>
          <DialogDescription>
            {seance.matiere} — {seance.salle}, {seance.heure_debut.slice(0, 5)}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col items-center gap-3 py-4 text-center">
          {geo.status === "loading" && (
            <>
              <div className="relative mb-2 flex h-[104px] w-[104px] items-center justify-center">
                <span className="animate-dc-pulse absolute inset-0 rounded-full bg-indigo-100" />
                <span className="animate-dc-pulse-delay absolute inset-0 rounded-full bg-indigo-100" />
                <div className="relative flex h-[68px] w-[68px] items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-[0_20px_40px_-14px_rgba(79,70,229,.5)]">
                  <MapPin className="h-7 w-7 text-white" strokeWidth={1.9} />
                </div>
              </div>
              <h2 className="font-display text-[19px] font-bold text-ink-900">
                Localisation en cours&hellip;
              </h2>
              <p className="max-w-[270px] text-[13.5px] leading-relaxed text-ink-500">
                Nous vérifions votre position par rapport à celle du délégué pour la salle{" "}
                {seance.salle}.
              </p>
            </>
          )}

          {geo.status === "success" && !checkIn.isSuccess && (
            <>
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50">
                <MapPin className="h-6 w-6 text-indigo-600" />
              </div>
              <p className="text-sm text-ink-500">
                Position obtenue. Confirmez pour valider votre présence — la distance avec le
                délégué est vérifiée côté serveur.
              </p>
            </>
          )}

          {checkIn.isSuccess && (
            <>
              <div className="animate-dc-pop relative mb-1 flex h-[88px] w-[88px] items-center justify-center">
                <span className="animate-dc-ray absolute top-0 left-1/2 h-3 w-[3px] -ml-[1.5px] rounded-full bg-emerald-500" />
                <span className="animate-dc-ray absolute top-2.5 right-0.5 h-3 w-[3px] rounded-full bg-emerald-500" />
                <span className="animate-dc-ray absolute top-1/2 right-0 h-[3px] w-3 -mt-[1.5px] rounded-full bg-emerald-500" />
                <span className="animate-dc-ray absolute bottom-2.5 right-0.5 h-3 w-[3px] rounded-full bg-emerald-500" />
                <span className="animate-dc-ray absolute bottom-2.5 left-0.5 h-3 w-[3px] rounded-full bg-emerald-500" />
                <span className="animate-dc-ray absolute top-1/2 left-0 h-[3px] w-3 -mt-[1.5px] rounded-full bg-emerald-500" />
                <div className="relative flex h-[76px] w-[76px] items-center justify-center rounded-full bg-emerald-500 shadow-[0_20px_40px_-14px_rgba(15,165,114,.4)]">
                  <CheckCircle2 className="h-8 w-8 text-white" strokeWidth={2.2} />
                </div>
              </div>
              <h2 className="font-display text-[20px] font-bold text-ink-900">
                Présence confirmée
              </h2>
              <p className="text-[13.5px] text-ink-500">
                À {checkIn.data?.distance}m du délégué &middot; {seance.matiere}
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

        <DialogFooter className={cn("gap-2", checkIn.isSuccess && "hidden")}>
          {geo.status === "error" && (
            <Button variant="outline" onClick={() => geo.locate()}>
              Réessayer
            </Button>
          )}
          <Button
            onClick={handleConfirm}
            disabled={!geo.coords || checkIn.isPending || checkIn.isSuccess}
            className="rounded-xl"
          >
            {checkIn.isPending ? "Envoi…" : "Confirmer ma présence"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
