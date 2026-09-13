"use client";

import { useCallback, useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

/**
 * - indisponible : ce navigateur ne sait pas recevoir de push (ou l'API n'a
 *   pas de clés) ; sur iPhone, c'est le cas tant que l'app n'est pas
 *   installée sur l'écran d'accueil.
 * - refuse : l'utilisateur a bloqué les notifications, on ne peut plus
 *   demander — il faut passer par les réglages du navigateur.
 */
export type EtatPush = "inconnu" | "indisponible" | "inactif" | "actif" | "refuse";

interface ClePublique {
  cle: string | null;
  disponible: boolean;
}

function versUint8Array(base64Url: string): Uint8Array {
  const base64 = (base64Url + "=".repeat((4 - (base64Url.length % 4)) % 4))
    .replace(/-/g, "+")
    .replace(/_/g, "/");
  const brut = atob(base64);
  return Uint8Array.from(brut, (c) => c.charCodeAt(0));
}

function supporteLePush(): boolean {
  return (
    typeof window !== "undefined" &&
    "serviceWorker" in navigator &&
    "PushManager" in window &&
    "Notification" in window
  );
}

async function enregistrerServiceWorker(): Promise<ServiceWorkerRegistration> {
  const existante = await navigator.serviceWorker.getRegistration("/");
  return existante ?? navigator.serviceWorker.register("/sw.js", { scope: "/" });
}

/**
 * Rappels de pointage sur le téléphone : abonnement Web Push de cet
 * appareil, envoyé à l'API. L'état vient du navigateur lui-même
 * (permission + abonnement existant), pas d'un drapeau stocké : il reste
 * juste si l'utilisateur change d'avis depuis ses réglages.
 */
export function usePush() {
  const { data: cle } = useQuery({
    queryKey: ["push", "cle-publique"],
    queryFn: () => apiFetch<ClePublique>("/api/push/cle-publique"),
    staleTime: Infinity,
  });
  const [etat, setEtat] = useState<EtatPush>("inconnu");
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  // L'état se lit dans le navigateur (permission + abonnement existant),
  // de façon asynchrone : le résultat arrive par le callback, jamais
  // pendant le rendu.
  const lireEtat = useCallback(async (): Promise<EtatPush> => {
    if (!supporteLePush() || (cle && !cle.disponible)) return "indisponible";
    if (Notification.permission === "denied") return "refuse";
    const enregistrement = await navigator.serviceWorker.getRegistration("/");
    const abonnement = await enregistrement?.pushManager.getSubscription();
    return abonnement && Notification.permission === "granted" ? "actif" : "inactif";
  }, [cle]);

  useEffect(() => {
    if (cle === undefined) return;
    let annule = false;
    lireEtat().then((e) => !annule && setEtat(e));
    return () => {
      annule = true;
    };
  }, [cle, lireEtat]);

  const relire = useCallback(async () => setEtat(await lireEtat()), [lireEtat]);

  const activer = useCallback(async () => {
    if (!cle?.cle) return;
    setEnCours(true);
    setErreur(null);
    try {
      const permission = await Notification.requestPermission();
      if (permission !== "granted") {
        setEtat(permission === "denied" ? "refuse" : "inactif");
        return;
      }
      const enregistrement = await enregistrerServiceWorker();
      await navigator.serviceWorker.ready;
      const abonnement =
        (await enregistrement.pushManager.getSubscription()) ??
        (await enregistrement.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: versUint8Array(cle.cle) as BufferSource,
        }));
      const json = abonnement.toJSON();
      await apiFetch("/api/me/push-subscriptions", {
        method: "POST",
        body: JSON.stringify({
          endpoint: abonnement.endpoint,
          keys: json.keys,
          content_encoding: (PushManager.supportedContentEncodings ?? ["aesgcm"])[0],
        }),
      });
      setEtat("actif");
    } catch (e) {
      setErreur(e instanceof Error ? e.message : "L'activation a échoué.");
      await relire();
    } finally {
      setEnCours(false);
    }
  }, [cle, relire]);

  const desactiver = useCallback(async () => {
    setEnCours(true);
    setErreur(null);
    try {
      const enregistrement = await navigator.serviceWorker.getRegistration("/");
      const abonnement = await enregistrement?.pushManager.getSubscription();
      if (abonnement) {
        await apiFetch("/api/me/push-subscriptions", {
          method: "DELETE",
          body: JSON.stringify({ endpoint: abonnement.endpoint }),
        }).catch(() => undefined);
        await abonnement.unsubscribe();
      }
      setEtat("inactif");
    } finally {
      setEnCours(false);
    }
  }, []);

  return { etat, enCours, erreur, activer, desactiver };
}
