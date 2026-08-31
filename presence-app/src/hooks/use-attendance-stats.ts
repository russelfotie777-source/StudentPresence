"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

export interface AttendanceStats {
  total_seances: number;
  presences: number;
  taux: number | null;
}

export function useAttendanceStats(enabled: boolean) {
  return useQuery({
    queryKey: ["attendance-stats", "me"],
    queryFn: () => apiFetch<AttendanceStats>("/api/me/attendance-stats"),
    enabled,
  });
}
