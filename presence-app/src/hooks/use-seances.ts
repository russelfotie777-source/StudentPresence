"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiFetch, ApiError } from "@/lib/api-client";
import type { RosterEntry, Seance } from "@/types/api";

function errorMessage(error: unknown, fallback: string) {
  return error instanceof ApiError ? error.message : fallback;
}

export function useTodaySeances() {
  return useQuery({
    queryKey: ["seances", "today"],
    queryFn: () => apiFetch<Seance[]>("/api/seances/today"),
    refetchInterval: 60_000, // la fenêtre active ±15min bouge avec l'horloge
  });
}

export function useHistorySeances() {
  return useQuery({
    queryKey: ["seances", "history"],
    queryFn: () => apiFetch<Seance[]>("/api/seances/history"),
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
    onSuccess: () => {
      invalidate();
      toast.success("Position envoyée aux étudiants.");
    },
    onError: (error) => toast.error(errorMessage(error, "Impossible d'envoyer la position.")),
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
    onSuccess: () => {
      invalidate();
      toast.success("Présence confirmée !");
    },
    onError: (error) => toast.error(errorMessage(error, "Le pointage a échoué.")),
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
    onSuccess: (_data, input) => {
      invalidate();
      toast.success(input.etat === "present" ? "Enseignant marqué présent." : "Enseignant marqué absent.");
    },
    onError: (error) => toast.error(errorMessage(error, "Le marquage a échoué.")),
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
    onSuccess: (_data, etat) => {
      invalidate();
      toast.success(etat === "present" ? "Marqué présent." : "Marqué absent.");
    },
    onError: (error) => toast.error(errorMessage(error, "Le marquage a échoué.")),
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
    onSuccess: () => {
      invalidate();
      toast.success("Effectif déclaré.");
    },
    onError: (error) => toast.error(errorMessage(error, "L'envoi a échoué.")),
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
      toast.success("Liste de présence confirmée et verrouillée.");
    },
    onError: (error) => toast.error(errorMessage(error, "La confirmation a échoué.")),
  });
}
