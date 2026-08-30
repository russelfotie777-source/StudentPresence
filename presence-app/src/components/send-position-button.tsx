"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { useGeolocation } from "@/hooks/use-geolocation";
import { useSendPosition } from "@/hooks/use-seances";

export function SendPositionButton({
  seanceId,
  alreadySent = false,
}: {
  seanceId: number;
  alreadySent?: boolean;
}) {
  const geo = useGeolocation();
  const sendPosition = useSendPosition(seanceId);
  const [sent, setSent] = useState(alreadySent);

  useEffect(() => {
    if (geo.status === "success" && geo.coords && !sent && !sendPosition.isPending) {
      sendPosition.mutate(geo.coords, { onSuccess: () => setSent(true) });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [geo.status, geo.coords]);

  if (sent) {
    return (
      <Button size="sm" variant="outline" disabled>
        📍 Position envoyée
      </Button>
    );
  }

  return (
    <div className="flex flex-col items-start gap-1">
      <Button
        size="sm"
        variant="outline"
        onClick={() => geo.locate()}
        disabled={geo.status === "loading" || sendPosition.isPending}
      >
        {geo.status === "loading" || sendPosition.isPending
          ? "Localisation…"
          : "📍 Envoyer ma position"}
      </Button>
      {geo.status === "error" && (
        <p className="max-w-xs text-xs text-destructive">{geo.errorMessage}</p>
      )}
    </div>
  );
}
