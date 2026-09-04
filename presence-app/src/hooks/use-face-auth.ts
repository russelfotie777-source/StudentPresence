"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError, setToken } from "@/lib/api-client";
import type { AuthResponse, MeResponse } from "@/types/api";

interface FacePayload {
  descriptor: number[];
}

function applyFullToken(
  queryClient: ReturnType<typeof useQueryClient>,
  data: AuthResponse,
) {
  if (data.token) setToken(data.token);
  queryClient.setQueryData(["me"], {
    user: data.user,
    face_pending: false,
    face_enrolled: true,
  } satisfies MeResponse);
}

export function useEnrollFace() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: FacePayload) =>
      apiFetch<AuthResponse>("/api/auth/face/enroll", {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    onSuccess: (data) => {
      applyFullToken(queryClient, data);
      toast.success("Visage enregistré, bienvenue !");
    },
    onError: (error) => {
      const message =
        error instanceof ApiError ? error.message : "Impossible d'enregistrer votre visage.";
      toast.error(message);
    },
  });
}

export function useVerifyFace() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: FacePayload) =>
      apiFetch<AuthResponse>("/api/auth/face/verify", {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    onSuccess: (data) => {
      applyFullToken(queryClient, data);
      toast.success("Identité vérifiée.");
    },
    onError: (error) => {
      const message =
        error instanceof ApiError
          ? error.status === 429
            ? "Trop de tentatives, réessayez dans une minute."
            : (error.errors?.descriptor?.[0] ?? error.message)
          : "Vérification impossible.";
      toast.error(message);
    },
  });
}
