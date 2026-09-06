"use client";

import { useInfiniteQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch } from "@/lib/api-client";
import { pageSuivante } from "@/lib/pagination";
import type { Paginated, RequeteEnseignant } from "@/types/api";

export function useMyRequetes() {
  return useInfiniteQuery({
    queryKey: ["requetes", "mine"],
    queryFn: ({ pageParam }) =>
      apiFetch<Paginated<RequeteEnseignant>>(`/api/requetes/mine?page=${pageParam}`),
    initialPageParam: 1,
    getNextPageParam: pageSuivante,
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
