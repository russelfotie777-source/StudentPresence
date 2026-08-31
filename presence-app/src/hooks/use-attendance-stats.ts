"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

export interface AttendanceStats {
  total_seances: number;
  presences: number;
  taux: number | null;
}

export interface AttendanceTrendPoint {
  semaine: number;
  label: string;
  taux: number;
}

export function useAttendanceStats(enabled: boolean) {
  return useQuery({
    queryKey: ["attendance-stats", "me"],
    queryFn: () => apiFetch<AttendanceStats>("/api/me/attendance-stats"),
    enabled,
  });
}

export function useAttendanceTrend(enabled: boolean) {
  return useQuery({
    queryKey: ["attendance-stats", "trend"],
    queryFn: () => apiFetch<AttendanceTrendPoint[]>("/api/me/attendance-trend"),
    enabled,
  });
}
