"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { MeResponse, User } from "@/types/api";

interface ReponseCompte {
  message: string;
  user: User;
  email_en_attente: string | null;
}

/** Chaque changement renvoie l'utilisateur à jour : on le pose directement dans « me ». */
function useMutationCompte<TInput>(path: string, method: "PUT" | "POST") {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: TInput) =>
      apiFetch<ReponseCompte>(path, { method, body: JSON.stringify(input ?? {}) }),
    onSuccess: (r) => {
      queryClient.setQueryData<MeResponse>(["me"], (ancien) =>
        ancien ? { ...ancien, user: r.user, email_en_attente: r.email_en_attente } : ancien,
      );
    },
  });
}

export interface ChangementMotDePasse {
  mot_de_passe_actuel: string;
  mot_de_passe: string;
  mot_de_passe_confirmation: string;
}

export function useChangerMotDePasse() {
  return useMutationCompte<ChangementMotDePasse>("/api/auth/compte/mot-de-passe", "PUT");
}

export function useChangerTelephone() {
  return useMutationCompte<{ telephone: string; mot_de_passe_actuel: string }>(
    "/api/auth/compte/telephone",
    "PUT",
  );
}

export function useDefinirEmail() {
  return useMutationCompte<{ email: string }>("/api/auth/compte/email", "PUT");
}

export function useRenvoyerCode() {
  return useMutationCompte<undefined>("/api/auth/compte/email/renvoyer", "POST");
}

export function useVerifierEmail() {
  return useMutationCompte<{ code: string }>("/api/auth/compte/email/verifier", "POST");
}

// --- mot de passe oublié (hors connexion) -----------------------------------

export function useDemanderCodeMotDePasse() {
  return useMutation({
    mutationFn: (input: { phone: string }) =>
      apiFetch<{ message: string; email_masque: string }>("/api/auth/mot-de-passe-oublie", {
        method: "POST",
        body: JSON.stringify(input),
      }),
  });
}

export function useReinitialiserMotDePasse() {
  return useMutation({
    mutationFn: (input: { phone: string; code: string; mot_de_passe: string; mot_de_passe_confirmation: string }) =>
      apiFetch<{ message: string }>("/api/auth/mot-de-passe-oublie/reinitialiser", {
        method: "POST",
        body: JSON.stringify(input),
      }),
  });
}
