"use client";

import Link from "next/link";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { useMyPayroll } from "@/hooks/use-payroll";

function formatFcfa(n: number) {
  return `${n.toLocaleString("fr-FR")} FCFA`;
}

export default function SalairePage() {
  const { data, isLoading } = useMyPayroll();

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-zinc-900">Mon salaire</h1>
        <Link href="/dashboard" className="text-sm text-violet-700 underline">
          Retour
        </Link>
      </div>

      {isLoading && <Skeleton className="h-24 w-full rounded-xl" />}

      {data && (
        <>
          <Card>
            <CardContent className="grid grid-cols-2 gap-4 py-4 text-center">
              <div>
                <p className="text-2xl font-bold text-violet-700">
                  {formatFcfa(data.total_salaire)}
                </p>
                <p className="text-xs text-zinc-500">Total</p>
              </div>
              <div>
                <p className="text-2xl font-bold text-red-600">
                  {formatFcfa(data.total_penalite_retard)}
                </p>
                <p className="text-xs text-zinc-500">Pénalités de retard</p>
              </div>
            </CardContent>
          </Card>

          <div className="flex flex-col gap-2">
            {data.lignes.map((ligne) => (
              <Card key={ligne.seance_id}>
                <CardContent className="flex items-center justify-between py-3">
                  <div>
                    <p className="text-sm font-medium">{ligne.matiere ?? "—"}</p>
                    <p className="text-xs text-zinc-500">
                      {ligne.date} · {ligne.salle} · {ligne.heure_debut}
                    </p>
                    {ligne.retard_minutes > 0 && (
                      <Badge variant="outline" className="mt-1 text-amber-700">
                        {ligne.retard_minutes} min de retard
                      </Badge>
                    )}
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-violet-700">
                      {formatFcfa(ligne.salaire)}
                    </p>
                    {ligne.penalite_retard > 0 && (
                      <>
                        <p className="text-xs text-red-600">
                          -{formatFcfa(ligne.penalite_retard)}
                        </p>
                        <Link
                          href={`/requetes?seance_id=${ligne.seance_id}`}
                          className="text-xs text-violet-700 underline"
                        >
                          Contester
                        </Link>
                      </>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
            {data.lignes.length === 0 && (
              <p className="mt-8 text-center text-sm text-zinc-500">
                Aucune séance rémunérée pour l&apos;instant.
              </p>
            )}
          </div>
        </>
      )}
    </div>
  );
}
