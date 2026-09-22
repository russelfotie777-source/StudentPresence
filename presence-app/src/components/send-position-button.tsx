"use client";

import { useEffect, useRef } from "react";
import { Check, MapPin, RefreshCw, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useGeolocation, type Coords } from "@/hooks/use-geolocation";
import { usePermission } from "@/hooks/use-permission";
import { PermissionRefusee } from "@/components/demande-permission";
import { useSendPosition } from "@/hooks/use-seances";

/**
 * La position de la salle, envoyée par le délégué. Le geste tient dans une
 * cellule (`cellule`) du groupe d'actions de la carte : « Position » tant
 * qu'elle n'est pas envoyée, « Envoyée » ensuite ; ce qui a besoin de plus
 * de place — précision, erreur, appel sans GPS — s'écrit sous le groupe
 * (`messages`). Les deux se prennent par le hook, pour rester ensemble.
 */
export function usePositionDelegue({
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

  const envoyee = alreadySent || isSuccess;
  const busy = geo.status === "loading" || isPending;
  const failed = geo.status === "error" || isError || refusee;

  const cellule = envoyee ? (
    <Button variant="outline" disabled data-fait>
      <Check size={16} /> Envoyée
    </Button>
  ) : (
    <Button variant="outline" onClick={() => geo.locate()} disabled={busy || refusee}>
      {failed ? <RefreshCw size={16} /> : <MapPin size={16} />}
      {busy ? "Recherche…" : failed ? "Réessayer" : "Position"}
    </Button>
  );

  const messages = envoyee ? null : (
    <>
      {refusee && <PermissionRefusee type="position" />}
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
            Vous pouvez constater les présences dans la liste, même sans position GPS.
          </p>
          <Button variant="secondary" onClick={onManualValidation}>
            <Users size={16} /> Faire l’appel sans GPS
          </Button>
        </>
      )}
    </>
  );

  return { cellule, messages, envoyee };
}
