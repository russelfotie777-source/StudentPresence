"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError } from "@/lib/api-client";
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

function messageErreur(error: unknown, repli: string) {
  return error instanceof ApiError ? error.message : repli;
}

/**
 * Les compteurs de la vue d'ensemble comptent les mêmes comptes : les
 * invalider aussi évite qu'elle annonce des validations en attente déjà
 * traitées.
 */
function useInvalider() {
  const queryClient = useQueryClient();

  return () => {
    queryClient.invalidateQueries({ queryKey: ["validations"] });
    queryClient.invalidateQueries({ queryKey: ["dashboard"] });
  };
}

export function useApproveUser() {
  const invalider = useInvalider();

  return useMutation({
    mutationFn: (user: User) =>
      apiFetch(`/api/validations/${user.id}/approve`, { method: "POST" }),
    onSuccess: (_data, user) => {
      invalider();
      toast.success(`${user.name} peut désormais se connecter.`);
    },
    onError: (error) => toast.error(messageErreur(error, "La validation a échoué.")),
  });
}

export function useRejectUser() {
  const invalider = useInvalider();

  return useMutation({
    mutationFn: (user: User) =>
      apiFetch(`/api/validations/${user.id}/reject`, { method: "POST" }),
    onSuccess: (_data, user) => {
      invalider();
      toast.success(`La demande de ${user.name} a été refusée.`);
    },
    onError: (error) => toast.error(messageErreur(error, "Le refus a échoué.")),
  });
}
