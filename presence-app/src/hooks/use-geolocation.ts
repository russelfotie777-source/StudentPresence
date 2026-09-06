"use client";

import { useCallback, useEffect, useRef, useState } from "react";

export type GeolocationErrorReason =
  | "permission_denied"
  | "position_unavailable"
  | "timeout"
  | "unsupported"
  | "unknown";

export interface Coords {
  latitude: number;
  longitude: number;
  /** Rayon d'incertitude en mètres, tel que fourni par le navigateur. */
  accuracy: number;
}

interface GeolocationState {
  status: "idle" | "loading" | "success" | "error";
  coords: Coords | null;
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
 * Précision visée avant de s'arrêter. En dessous, continuer à attendre
 * n'apporte plus rien d'utile face à un périmètre de 120 m.
 */
const PRECISION_VISEE_METRES = 20;

/** Durée maximale de convergence avant de retenir le meilleur point obtenu. */
const DUREE_MAX_MS = 12_000;

/**
 * Localisation par convergence plutôt que par lecture unique.
 *
 * `getCurrentPosition` rend le PREMIER point disponible, et `enableHighAccuracy`
 * ne fait que demander la puce GPS — il n'attend pas qu'elle soit prête. Sur
 * mobile ce premier point est presque toujours une triangulation Wi-Fi/antenne
 * portant ±50 à 2000 m d'incertitude, avant que le GPS ne converge vers ±5 à
 * 15 m en quelques secondes. Comparer une telle mesure à un périmètre de 120 m
 * n'a aucun sens.
 *
 * On écoute donc les positions successives (`watchPosition`) en gardant la plus
 * précise, et on s'arrête dès que la précision visée est atteinte, ou au bout
 * de DUREE_MAX_MS avec le meilleur point obtenu — mieux vaut un point moyen que
 * pas de point du tout, le serveur reste juge de ce qu'il accepte.
 */
export function useGeolocation() {
  const [state, setState] = useState<GeolocationState>({
    status: "idle",
    coords: null,
    error: null,
  });

  const watchIdRef = useRef<number | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const meilleurRef = useRef<Coords | null>(null);

  const arreter = useCallback(() => {
    if (watchIdRef.current !== null) {
      navigator.geolocation.clearWatch(watchIdRef.current);
      watchIdRef.current = null;
    }
    if (timeoutRef.current !== null) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
  }, []);

  // Une localisation abandonnée en cours de route (dialogue refermé, écran
  // quitté) laisserait sinon le GPS actif en tâche de fond, à vider la batterie.
  useEffect(() => arreter, [arreter]);

  const locate = useCallback(
    (options?: PositionOptions) => {
      if (!("geolocation" in navigator)) {
        setState({ status: "error", coords: null, error: "unsupported" });
        return;
      }

      arreter();
      meilleurRef.current = null;
      setState({ status: "loading", coords: null, error: null });

      const terminer = () => {
        arreter();
        const meilleur = meilleurRef.current;
        setState(
          meilleur
            ? { status: "success", coords: meilleur, error: null }
            : { status: "error", coords: null, error: "timeout" },
        );
      };

      timeoutRef.current = setTimeout(terminer, DUREE_MAX_MS);

      watchIdRef.current = navigator.geolocation.watchPosition(
        (position) => {
          const candidat: Coords = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
          };

          const meilleur = meilleurRef.current;
          if (!meilleur || candidat.accuracy < meilleur.accuracy) {
            meilleurRef.current = candidat;
            // Affiché pendant la convergence : l'utilisateur voit la précision
            // se resserrer au lieu d'attendre devant un écran figé.
            setState({ status: "loading", coords: candidat, error: null });
          }

          if (meilleurRef.current!.accuracy <= PRECISION_VISEE_METRES) {
            terminer();
          }
        },
        (error) => {
          // Une erreur ponctuelle alors qu'on a déjà un point exploitable ne
          // doit pas faire perdre ce point.
          if (meilleurRef.current) {
            terminer();
            return;
          }

          arreter();

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
        { enableHighAccuracy: true, timeout: DUREE_MAX_MS, maximumAge: 0, ...options },
      );
    },
    [arreter],
  );

  return {
    ...state,
    errorMessage: state.error ? ERROR_MESSAGES[state.error] : null,
    /** Précision courante en mètres, arrondie — `null` tant qu'aucun point n'est arrivé. */
    precision: state.coords ? Math.round(state.coords.accuracy) : null,
    locate,
  };
}
