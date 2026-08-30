"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch, getToken, setToken } from "@/lib/api-client";
import type { AuthResponse, User } from "@/types/api";

export interface LoginInput {
  phone: string;
  password: string;
}

export function useMe() {
  return useQuery({
    queryKey: ["me"],
    queryFn: () => apiFetch<{ user: User }>("/api/auth/me"),
    enabled: !!getToken(),
    retry: false,
    staleTime: 60_000,
  });
}

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input: LoginInput) => {
      const data = await apiFetch<AuthResponse>("/api/auth/login", {
        method: "POST",
        body: JSON.stringify(input),
      });

      // Pas d'inscription publique pour le rôle Admin (voir la commande
      // artisan app:make-admin côté backend) — on refuse ici toute connexion
      // d'un compte non-admin, même si les identifiants sont valides.
      if (data.user.role !== "Admin") {
        setToken(null);
        throw new Error("Ce compte n'a pas accès au back-office administrateur.");
      }

      return data;
    },
    onSuccess: (data) => {
      if (data.token) setToken(data.token);
      queryClient.setQueryData(["me"], { user: data.user });
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
