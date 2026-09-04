"use client";

import { useCallback, useEffect, useRef, useState } from "react";

export type FaceCameraErrorReason =
  | "permission_denied"
  | "no_camera"
  | "unsupported"
  | "unknown";

const ERROR_MESSAGES: Record<FaceCameraErrorReason, string> = {
  permission_denied:
    "L'accès à la caméra a été refusé. Autorisez-la dans les réglages de votre navigateur puis réessayez.",
  no_camera: "Aucune caméra détectée sur cet appareil.",
  unsupported: "Votre navigateur ne supporte pas l'accès à la caméra.",
  unknown: "Impossible d'accéder à la caméra.",
};

/**
 * Mirroir de use-geolocation.ts (même forme d'état start/status/error) pour
 * une caméra frontale. Résolution volontairement réduite : suffisante pour
 * la détection de visage, plus rapide à traiter côté navigateur.
 */
export function useFaceCamera() {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  // Incrémenté à chaque start()/démontage pour invalider les appels en vol :
  // en dev, StrictMode rejoue monter -> nettoyer -> remonter, ce qui peut
  // déclencher deux getUserMedia() qui se recouvrent ; sans ce garde-fou, le
  // premier à se résoudre après coup écrase l'état (caméra prête) posé par
  // le second, y compris avec une erreur périmée.
  const requestIdRef = useRef(0);
  const [status, setStatus] = useState<"idle" | "loading" | "ready" | "error">("idle");
  const [error, setError] = useState<FaceCameraErrorReason | null>(null);

  const stop = useCallback(() => {
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
  }, []);

  const start = useCallback(async () => {
    const requestId = ++requestIdRef.current;

    if (!navigator.mediaDevices?.getUserMedia) {
      setStatus("error");
      setError("unsupported");
      return;
    }

    setStatus("loading");
    setError(null);

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user", width: { ideal: 480 }, height: { ideal: 480 } },
        audio: false,
      });

      if (requestIdRef.current !== requestId) {
        stream.getTracks().forEach((track) => track.stop());
        return;
      }

      streamRef.current = stream;

      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }

      setStatus("ready");
    } catch (err) {
      if (requestIdRef.current !== requestId) return;

      const name = err instanceof DOMException ? err.name : "";

      setStatus("error");
      setError(
        name === "NotAllowedError" || name === "PermissionDeniedError"
          ? "permission_denied"
          : name === "NotFoundError"
            ? "no_camera"
            : "unknown",
      );
    }
  }, []);

  // requestIdRef est un compteur de génération, pas une référence DOM : le
  // lire/incrémenter au démontage est le but recherché (invalider start()).
  useEffect(() => {
    return () => {
      requestIdRef.current++;
      stop();
    };
  }, [stop]);

  return {
    videoRef,
    status,
    error,
    errorMessage: error ? ERROR_MESSAGES[error] : null,
    start,
    stop,
  };
}
