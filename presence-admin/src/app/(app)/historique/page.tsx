"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { apiFetch } from "@/lib/api-client";
import type { Seance } from "@/types/api";

interface HistoriqueResponse {
  seances: Seance[];
  stats: { total: number; present: number; absent: number };
}

export default function HistoriquePage() {
  const [salleId, setSalleId] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["historique-seances", salleId],
    queryFn: () =>
      apiFetch<HistoriqueResponse>(
        `/api/historique-seances${salleId ? `?salle_id=${salleId}` : ""}`,
      ),
  });

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold text-zinc-900">Historique des séances</h1>

      {data && (
        <div className="flex gap-4 text-sm text-zinc-600">
          <span>Total : <strong>{data.stats.total}</strong></span>
          <span className="text-green-700">Présent : <strong>{data.stats.present}</strong></span>
          <span className="text-red-700">Absent : <strong>{data.stats.absent}</strong></span>
        </div>
      )}

      <Card>
        <CardContent className="py-4">
          {isLoading && <p className="text-sm text-zinc-500">Chargement…</p>}
          {data && (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Matière</TableHead>
                  <TableHead>Salle</TableHead>
                  <TableHead>Enseignant</TableHead>
                  <TableHead>Horaire</TableHead>
                  <TableHead>État</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.seances.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell>{s.date_seance ?? "—"}</TableCell>
                    <TableCell>{s.matiere ?? "—"}</TableCell>
                    <TableCell>{s.salle}</TableCell>
                    <TableCell>{s.enseignant}</TableCell>
                    <TableCell>
                      {s.heure_debut}–{s.heure_fin}
                    </TableCell>
                    <TableCell>
                      <Badge
                        className={
                          s.etat_final === "present"
                            ? "bg-green-100 text-green-800"
                            : "bg-red-100 text-red-800"
                        }
                      >
                        {s.etat_final === "present" ? "Présent" : "Absent"}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
