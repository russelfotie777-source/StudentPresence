"use client";

import { useCallback, useEffect, useState } from "react";

/**
 * `inconnu` n'est pas un défaut d'implémentation : Safari ne répond pas à
 * l'interrogation des permissions pour la géolocalisation. Dans ce cas on ne
 * peut pas savoir avant d'avoir demandé, et l'interface doit l'assumer plutôt
 * que d'afficher un état faux.
 */
export type EtatPermission = "accordee" | "a_demander" | "refusee" | "inconnu";

const NOMS: Record<"position" | "camera", PermissionName> = {
  position: "geolocation" as PermissionName,
  camera: "camera" as PermissionName,
};

function versEtat(state: PermissionStatus["state"]): EtatPermission {
  if (state === "granted") return "accordee";
  if (state === "denied") return "refusee";

  return "a_demander";
}

/**
 * État d'une permission navigateur, tenu à jour en direct.
 *
 * L'abonnement à l'évènement `change` compte autant que la lecture initiale :
 * il permet à l'écran de se mettre à jour tout seul quand l'utilisateur part
 * autoriser la permission dans ses réglages puis revient, sans qu'il ait à
 * recharger la page — c'est ce comportement qui donne la sensation d'une app
 * native.
 */
export function usePermission(type: "position" | "camera") {
  const [etat, setEtat] = useState<EtatPermission>("inconnu");

  useEffect(() => {
    let statut: PermissionStatus | null = null;
    let annule = false;

    const surChangement = () => {
      if (statut && !annule) setEtat(versEtat(statut.state));
    };

    async function interroger() {
      if (typeof navigator === "undefined" || !navigator.permissions?.query) return;

      try {
        statut = await navigator.permissions.query({ name: NOMS[type] });
        if (annule) return;

        setEtat(versEtat(statut.state));
        statut.addEventListener("change", surChangement);
      } catch {
        // Nom de permission non reconnu (cas de Safari pour la
        // géolocalisation) : on reste sur "inconnu".
      }
    }

    interroger();

    return () => {
      annule = true;
      statut?.removeEventListener("change", surChangement);
    };
  }, [type]);

  /**
   * À appeler quand une tentative réelle a tranché ce que l'interrogation ne
   * savait pas dire — indispensable là où l'état reste "inconnu".
   */
  const noterResultat = useCallback((accordee: boolean) => {
    setEtat(accordee ? "accordee" : "refusee");
  }, []);

  return { etat, noterResultat };
}
