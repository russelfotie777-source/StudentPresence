"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { User } from "@/types/api";

export function usePendingUsers(role?: "Delegue" | "Enseignant") {
  return useQuery({
    queryKey: ["validations", role ?? "all"],
    queryFn: () =>
      apiFetch<User[]>(
        `/api/validations?statut=pending${role ? `&role=${role}` : ""}`,
      ),
  });
}

export function useApproveUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (userId: number) =>
      apiFetch(`/api/validations/${userId}/approve`, { method: "POST" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["validations"] }),
  });
}

export function useRejectUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (userId: number) =>
      apiFetch(`/api/validations/${userId}/reject`, { method: "POST" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["validations"] }),
  });
}
