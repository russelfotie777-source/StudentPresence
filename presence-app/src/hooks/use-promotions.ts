"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

export interface StudentSearchResult {
  id: number;
  name: string;
  salle: string | null;
  filiere: string | null;
  niveau: string | null;
  has_active_promotion: boolean;
}

export function useStudentSearch(search: string) {
  return useQuery({
    queryKey: ["students", "search", search],
    queryFn: () =>
      apiFetch<StudentSearchResult[]>(
        `/api/students/search?search=${encodeURIComponent(search)}`,
      ),
    enabled: search.length > 1,
  });
}

export interface ActivePromotion {
  id: number;
  etudiant: { id: number; name: string; salle: { nom: string } | null };
  date_fin: string;
  duree_minutes: number;
}

export function useActivePromotions() {
  return useQuery({
    queryKey: ["promotions"],
    queryFn: () => apiFetch<ActivePromotion[]>("/api/promotions"),
  });
}

export function useCreatePromotion() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: { etudiant_id: number; duree_minutes: number }) =>
      apiFetch("/api/promotions", { method: "POST", body: JSON.stringify(input) }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["promotions"] });
      queryClient.invalidateQueries({ queryKey: ["students", "search"] });
    },
  });
}
