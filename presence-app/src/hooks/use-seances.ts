"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { RosterEntry, Seance } from "@/types/api";

export function useTodaySeances() {
  return useQuery({
    queryKey: ["seances", "today"],
    queryFn: () => apiFetch<Seance[]>("/api/seances/today"),
    refetchInterval: 60_000, // la fenêtre active ±15min bouge avec l'horloge
  });
}

function useInvalidateToday() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["seances", "today"] });
}

export function useSendPosition(seanceId: number) {
  const invalidate = useInvalidateToday();

  return useMutation({
    mutationFn: (coords: { latitude: number; longitude: number }) =>
      apiFetch(`/api/seances/${seanceId}/position`, {
        method: "POST",
        body: JSON.stringify(coords),
      }),
    onSuccess: invalidate,
  });
}

export function useCheckIn(seanceId: number) {
  const invalidate = useInvalidateToday();

  return useMutation({
    mutationFn: (coords: { latitude: number; longitude: number }) =>
      apiFetch<{ distance: number }>(`/api/seances/${seanceId}/check-in`, {
        method: "POST",
        body: JSON.stringify(coords),
      }),
    onSuccess: invalidate,
  });
}

export function useMarkDelegue(seanceId: number) {
  const invalidate = useInvalidateToday();

  return useMutation({
    mutationFn: (input: {
      etat: "present" | "absent";
      set_debut_reel?: boolean;
      set_fin_reelle?: boolean;
    }) =>
      apiFetch(`/api/seances/${seanceId}/mark-delegue`, {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: invalidate,
  });
}

export function useMarkProf(seanceId: number) {
  const invalidate = useInvalidateToday();

  return useMutation({
    mutationFn: (etat: "present" | "absent") =>
      apiFetch(`/api/seances/${seanceId}/mark-prof`, {
        method: "POST",
        body: JSON.stringify({ etat }),
      }),
    onSuccess: invalidate,
  });
}

export function usePush(seanceId: number) {
  const invalidate = useInvalidateToday();

  return useMutation({
    mutationFn: (etudiants_presents: number) =>
      apiFetch(`/api/seances/${seanceId}/push`, {
        method: "POST",
        body: JSON.stringify({ etudiants_presents }),
      }),
    onSuccess: invalidate,
  });
}

export function useRoster(seanceId: number, enabled: boolean) {
  return useQuery({
    queryKey: ["seances", seanceId, "roster"],
    queryFn: () => apiFetch<RosterEntry[]>(`/api/seances/${seanceId}/roster`),
    enabled,
  });
}

export function useConfirmRoster(seanceId: number) {
  const invalidate = useInvalidateToday();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (etudiants: number[]) =>
      apiFetch(`/api/seances/${seanceId}/confirm-roster`, {
        method: "POST",
        body: JSON.stringify({ etudiants }),
      }),
    onSuccess: () => {
      invalidate();
      queryClient.invalidateQueries({ queryKey: ["seances", seanceId, "roster"] });
    },
  });
}
