"use client";

import { useCallback, useEffect, useRef, useState } from "react";

export type GeolocationErrorReason =
  | "permission_denied"
  | "position_unavailable"
  | "timeout"
  | "unsupported"
  | "imprecise"
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
    "L'accès à la position a été refusé. Autorisez la localisation dans les réglages de votre navigateur, puis réessayez.",
  position_unavailable:
    "Votre téléphone ne trouve pas sa position. Activez la localisation dans ses réglages, puis réessayez.",
  timeout:
    "Votre téléphone n'a rendu aucune position. Activez la localisation dans ses réglages, puis réessayez.",
  unsupported: "Votre navigateur ne sait pas donner votre position.",
  imprecise:
    "Votre téléphone ne trouve pas sa position, seulement la ville. Activez la localisation dans ses réglages, puis réessayez.",
  unknown: "La localisation n'a pas abouti. Réessayez.",
};

/** En dessous de cette incertitude, inutile d'attendre davantage : on prend. */
const PRECISION_SUFFISANTE_M = 30;

/** Temps laissé au GPS pour se resserrer avant de prendre ce qu'on a. */
const DUREE_MAX_MS = 15_000;

/**
 * Localisation par convergence plutôt que par lecture unique.
 *
 * `getCurrentPosition` rend le PREMIER point disponible, et `enableHighAccuracy`
 * ne fait que demander la puce GPS — il n'attend pas qu'elle soit prête. Sur
 * mobile ce premier point est presque toujours une triangulation Wi-Fi/antenne
 * portant ±50 à 2000 m d'incertitude, avant que le GPS ne converge vers ±5 à
 * 15 m en quelques secondes.
 *
 * On écoute donc les positions successives (`watchPosition`) en gardant la plus
 * précise. Une mesure serrée termine la recherche tout de suite ; sinon, à
 * l'échéance, on garde la meilleure telle quelle : le pointage doit marcher
 * depuis la salle avec le téléphone qu'on a, et le serveur tient compte de
 * l'incertitude. Seule une mesure au-delà du plafond (`maxAccuracy`, celui
 * du serveur) est rendue en erreur — elle ne vient plus du téléphone mais de
 * l'adresse IP, et la localisation est sans doute coupée.
 */
export function useGeolocation(maxAccuracy = 2000) {
  const [state, setState] = useState<GeolocationState>({
    status: "idle",
    coords: null,
    error: null,
  });

  const watchIdRef = useRef<number | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const meilleurRef = useRef<Coords | null>(null);
  const generationRef = useRef(0);

  const arreter = useCallback(() => {
    generationRef.current++;
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
      arreter();
      if (!("geolocation" in navigator)) {
        setState({ status: "error", coords: null, error: "unsupported" });
        return;
      }

      const generation = generationRef.current;
      meilleurRef.current = null;
      setState({ status: "loading", coords: null, error: null });

      const terminer = () => {
        if (generation !== generationRef.current) return;
        arreter();
        const meilleur = meilleurRef.current;
        setState(
          meilleur && meilleur.accuracy <= maxAccuracy
            ? { status: "success", coords: meilleur, error: null }
            : {
                status: "error",
                coords: meilleur,
                error: meilleur ? "imprecise" : "timeout",
              },
        );
      };

      timeoutRef.current = setTimeout(terminer, DUREE_MAX_MS);

      const watchId = navigator.geolocation.watchPosition(
        (position) => {
          if (generation !== generationRef.current) return;
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

          if (meilleurRef.current!.accuracy <= PRECISION_SUFFISANTE_M) {
            terminer();
          }
        },
        (error) => {
          if (generation !== generationRef.current) return;
          // Une indisponibilité temporaire peut être suivie d'une mesure
          // valide ; l'échéance tranchera avec ce qui sera arrivé.
          if (
            error.code === error.POSITION_UNAVAILABLE ||
            error.code === error.TIMEOUT
          )
            return;

          arreter();
          setState({
            status: "error",
            coords: null,
            error: error.code === error.PERMISSION_DENIED ? "permission_denied" : "unknown",
          });
        },
        {
          enableHighAccuracy: true,
          timeout: DUREE_MAX_MS,
          // Un point vieux de quelques secondes vaut encore : on n'a pas
          // changé de salle entre-temps, et il évite d'attendre un premier
          // fix à froid.
          maximumAge: 10_000,
          ...options,
        },
      );
      if (generation === generationRef.current) watchIdRef.current = watchId;
      else navigator.geolocation.clearWatch(watchId);
    },
    [arreter, maxAccuracy],
  );

  return {
    ...state,
    errorMessage: state.error ? ERROR_MESSAGES[state.error] : null,
    /** Précision courante en mètres, arrondie — `null` tant qu'aucun point n'est arrivé. */
    precision: state.coords ? Math.round(state.coords.accuracy) : null,
    locate,
  };
}
