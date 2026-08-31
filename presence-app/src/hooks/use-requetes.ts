"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch } from "@/lib/api-client";
import type { RequeteEnseignant } from "@/types/api";

export function useMyRequetes() {
  return useQuery({
    queryKey: ["requetes", "mine"],
    queryFn: () => apiFetch<RequeteEnseignant[]>("/api/requetes/mine"),
  });
}

export function useSubmitRequete() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (form: FormData) =>
      apiFetch<RequeteEnseignant>("/api/requetes", {
        method: "POST",
        body: form,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["requetes", "mine"] });
      toast.success("Requête envoyée.");
    },
  });
}
