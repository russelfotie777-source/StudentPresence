"use client";

import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { useMyPayroll } from "@/hooks/use-payroll";

function formatFcfa(n: number) {
  return `${n.toLocaleString("fr-FR")} FCFA`;
}

export default function SalairePage() {
  const { data, isLoading } = useMyPayroll();

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">Mon salaire</h1>

      {isLoading && <Skeleton className="h-28 w-full rounded-2xl" />}

      {data && (
        <>
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-2xl border border-border bg-card p-4 text-center">
              <p className="text-2xl font-bold text-primary">{formatFcfa(data.total_salaire)}</p>
              <p className="mt-1 text-xs text-muted-foreground">Total gagné</p>
            </div>
            <div className="rounded-2xl border border-border bg-card p-4 text-center">
              <p className="text-2xl font-bold text-destructive">
                {formatFcfa(data.total_penalite_retard)}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">Pénalités de retard</p>
            </div>
          </div>

          <div className="flex flex-col gap-2.5">
            {data.lignes.map((ligne) => (
              <div
                key={ligne.seance_id}
                className="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card p-4"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-foreground">
                    {ligne.matiere ?? "—"}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {ligne.date} · {ligne.salle} · {ligne.heure_debut}
                  </p>
                  {ligne.retard_minutes > 0 && (
                    <span className="mt-1.5 inline-flex items-center gap-1 rounded-full bg-warning/20 px-2 py-0.5 text-[11px] font-medium text-warning-foreground">
                      <AlertTriangle className="h-3 w-3" />
                      {ligne.retard_minutes} min de retard
                    </span>
                  )}
                </div>
                <div className="shrink-0 text-right">
                  <p className="font-semibold text-foreground">{formatFcfa(ligne.salaire)}</p>
                  {ligne.penalite_retard > 0 && (
                    <>
                      <p className="text-xs text-destructive">
                        -{formatFcfa(ligne.penalite_retard)}
                      </p>
                      <Link
                        href={`/requetes?seance_id=${ligne.seance_id}`}
                        className="text-xs font-medium text-primary"
                      >
                        Contester
                      </Link>
                    </>
                  )}
                </div>
              </div>
            ))}
            {data.lignes.length === 0 && (
              <p className="mt-8 text-center text-sm text-muted-foreground">
                Aucune séance rémunérée pour l&apos;instant.
              </p>
            )}
          </div>
        </>
      )}
    </div>
  );
}
