"use client";

import { useEffect, useState } from "react";
import { detectFaceLive, type LiveFaceHint } from "@/lib/face-recognition";

const TICK_MS = 300;

/**
 * Boucle de suivi léger pendant que la caméra tourne, pour le guide de
 * cadrage en temps réel. Auto-planifiée via setTimeout (pas setInterval) :
 * un appel ne redémarre qu'une fois le précédent terminé, donc jamais
 * d'empilement si un appareil est lent à traiter une frame.
 */
export function useLiveFaceTracking(
  videoRef: React.RefObject<HTMLVideoElement | null>,
  active: boolean,
): LiveFaceHint | null {
  const [hint, setHint] = useState<LiveFaceHint | null>(null);

  useEffect(() => {
    if (!active) {
      // Rien à programmer ; la remise à null se fait via le nettoyage de
      // l'exécution précédente ci-dessous (couvre aussi bien le passage à
      // inactif que le démontage), plutôt qu'un setState synchrone ici.
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout>;

    async function tick() {
      const video = videoRef.current;

      if (video) {
        try {
          const result = await detectFaceLive(video);
          if (!cancelled) setHint(result);
        } catch {
          if (!cancelled) setHint(null);
        }
      }

      if (!cancelled) timer = setTimeout(tick, TICK_MS);
    }

    tick();

    return () => {
      cancelled = true;
      clearTimeout(timer);
      setHint(null);
    };
  }, [active, videoRef]);

  return hint;
}
