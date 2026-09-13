"use client";

import { useEffect, useRef } from "react";
import { MapPin, RefreshCw, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useGeolocation, type Coords } from "@/hooks/use-geolocation";
import { usePermission } from "@/hooks/use-permission";
import { PermissionRefusee } from "@/components/demande-permission";
import { useSendPosition } from "@/hooks/use-seances";

export function SendPositionButton({
  seanceId,
  alreadySent = false,
  maxAccuracy = 50,
  onManualValidation,
}: {
  seanceId: number;
  alreadySent?: boolean;
  maxAccuracy?: number;
  onManualValidation?: () => void;
}) {
  const geo = useGeolocation(maxAccuracy);
  const { mutate, isPending, isSuccess, isError } = useSendPosition(seanceId);
  const submitted = useRef<Coords | null>(null);
  const { etat } = usePermission("position");
  const refusee = geo.error === "permission_denied" || etat === "refusee";

  useEffect(() => {
    if (
      geo.status === "success" &&
      geo.coords &&
      !alreadySent &&
      submitted.current !== geo.coords
    ) {
      submitted.current = geo.coords;
      mutate(geo.coords);
    }
  }, [geo.status, geo.coords, alreadySent, mutate]);

  if (alreadySent || isSuccess)
    return (
      <Button size="sm" variant="outline" disabled>
        <MapPin size={16} /> Position envoyée
      </Button>
    );
  const busy = geo.status === "loading" || isPending;
  const failed = geo.status === "error" || isError || refusee;

  return (
    <div className="gps-position-control flex w-full flex-col gap-2">
      {refusee ? (
        <PermissionRefusee type="position" />
      ) : (
        <Button variant="outline" onClick={() => geo.locate()} disabled={busy}>
          {failed ? <RefreshCw size={16} /> : <MapPin size={16} />}
          {busy
            ? "Localisation en cours…"
            : failed
              ? "Réessayer la localisation"
              : "Envoyer ma position"}
        </Button>
      )}
      {geo.precision !== null && (
        <p className="text-xs" aria-live="polite">
          Précision ±{geo.precision} m
          {geo.status === "loading" ? ", recherche en cours…" : ""}
        </p>
      )}
      {geo.status === "error" && !refusee && (
        <p className="text-xs" role="status">
          {geo.error === "imprecise"
            ? "La localisation reste trop imprécise pour toute la classe."
            : geo.errorMessage}
        </p>
      )}
      {failed && !busy && onManualValidation && (
        <>
          <p className="text-xs">
            Vous pouvez constater les présences dans la liste, même sans
            position GPS.
          </p>
          <Button variant="secondary" onClick={onManualValidation}>
            <Users size={16} /> Faire l’appel sans GPS
          </Button>
        </>
      )}
    </div>
  );
}
