"use client";

import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";
import type { PayrollSummary } from "@/types/api";

export function useMyPayroll() {
  return useQuery({
    queryKey: ["payroll", "me"],
    queryFn: () => apiFetch<PayrollSummary>("/api/payroll/me"),
  });
}
