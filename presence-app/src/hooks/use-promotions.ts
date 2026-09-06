"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch } from "@/lib/api-client";

export interface StudentSearchResult {
  id: number;
  name: string;
  salle: string | null;
  filiere: string | null;
  niveau: string | null;
  has_active_promotion: boolean;
}

export function useStudentSearch(search: string, salleId?: number) {
  return useQuery({
    queryKey: ["students", "search", salleId, search],
    queryFn: () =>
      apiFetch<StudentSearchResult[]>(
        `/api/students/search?search=${encodeURIComponent(search)}&salle_id=${salleId}`,
      ),
    enabled: salleId !== undefined,
  });
}

export interface TeacherSalle {
  id: number;
  nom: string;
  filiere: string | null;
  formation: string | null;
}

export function useTeacherSalles(enabled: boolean) {
  return useQuery({
    queryKey: ["teacher-salles"],
    queryFn: () => apiFetch<TeacherSalle[]>("/api/me/salles-enseignees"),
    enabled,
    staleTime: 5 * 60_000,
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
    mutationFn: (input: { etudiant_id: number; duree_minutes: number; salle_id?: number }) =>
      apiFetch("/api/promotions", { method: "POST", body: JSON.stringify(input) }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["promotions"] });
      queryClient.invalidateQueries({ queryKey: ["students", "search"] });
      toast.success("Promotion temporaire accordée.");
    },
  });
}
