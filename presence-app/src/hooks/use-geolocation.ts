"use client";

import { useCallback, useState } from "react";

export type GeolocationErrorReason =
  | "permission_denied"
  | "position_unavailable"
  | "timeout"
  | "unsupported"
  | "unknown";

interface GeolocationState {
  status: "idle" | "loading" | "success" | "error";
  coords: { latitude: number; longitude: number } | null;
  error: GeolocationErrorReason | null;
}

const ERROR_MESSAGES: Record<GeolocationErrorReason, string> = {
  permission_denied:
    "L'accès à la position a été refusé. Autorisez la géolocalisation dans les réglages de votre navigateur puis réessayez.",
  position_unavailable:
    "Impossible de déterminer votre position. Vérifiez que le GPS est activé.",
  timeout: "La localisation prend trop de temps. Vérifiez votre signal GPS et réessayez.",
  unsupported: "Votre navigateur ne supporte pas la géolocalisation.",
  unknown: "Une erreur inattendue est survenue lors de la localisation.",
};

/**
 * Remplace le geolocation flow de l'ancienne app (dashEtudiant.php), qui
 * définissait une fonction retryGeolocation() jamais réellement câblée à un
 * bouton — ici le retour "error" + un bouton "Réessayer" fonctionnent
 * vraiment.
 */
export function useGeolocation() {
  const [state, setState] = useState<GeolocationState>({
    status: "idle",
    coords: null,
    error: null,
  });

  const locate = useCallback((options?: PositionOptions) => {
    if (!("geolocation" in navigator)) {
      setState({ status: "error", coords: null, error: "unsupported" });
      return;
    }

    setState((s) => ({ ...s, status: "loading", error: null }));

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setState({
          status: "success",
          coords: {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          },
          error: null,
        });
      },
      (error) => {
        const reason: GeolocationErrorReason =
          error.code === error.PERMISSION_DENIED
            ? "permission_denied"
            : error.code === error.POSITION_UNAVAILABLE
              ? "position_unavailable"
              : error.code === error.TIMEOUT
                ? "timeout"
                : "unknown";

        setState({ status: "error", coords: null, error: reason });
      },
      { enableHighAccuracy: true, timeout: 15_000, maximumAge: 0, ...options },
    );
  }, []);

  return {
    ...state,
    errorMessage: state.error ? ERROR_MESSAGES[state.error] : null,
    locate,
  };
}
