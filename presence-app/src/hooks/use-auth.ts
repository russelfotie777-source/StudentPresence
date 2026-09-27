"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch, getToken, setToken } from "@/lib/api-client";
import type { AuthResponse, MeResponse, User } from "@/types/api";

export interface LoginInput {
  phone: string;
  password: string;
}

export function useMe() {
  return useQuery({
    queryKey: ["me"],
    queryFn: () => apiFetch<MeResponse>("/api/auth/me"),
    enabled: !!getToken(),
    retry: false,
    staleTime: 60_000,
  });
}

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: LoginInput) =>
      apiFetch<AuthResponse>("/api/auth/login", {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: (data) => {
      if (data.token) setToken(data.token);
      queryClient.setQueryData(["me"], {
        user: data.user,
        face_pending: data.requires_face ?? false,
        face_enrolled: data.face_enrolled ?? false,
      } satisfies MeResponse);
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => apiFetch("/api/auth/logout", { method: "POST" }),
    onSettled: () => {
      setToken(null);
      queryClient.setQueryData(["me"], undefined);
      queryClient.clear();
    },
  });
}

export function useRequestValidation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () =>
      apiFetch<{ user: User }>("/api/auth/request-validation", {
        method: "POST",
      }),
    onSuccess: (data) => {
      queryClient.setQueryData(["me"], data);
    },
  });
}
