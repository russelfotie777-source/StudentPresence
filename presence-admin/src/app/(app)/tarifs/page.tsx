"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { apiFetch } from "@/lib/api-client";

interface NiveauAvecTarif {
  id: number;
  nom: string;
  tarif_heure?: { tarif_heure: string } | null;
}

export default function TarifsPage() {
  const { data: niveaux, isLoading } = useQuery({
    queryKey: ["tarifs-heures"],
    queryFn: () => apiFetch<NiveauAvecTarif[]>("/api/tarifs-heures"),
  });

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold text-zinc-900">Tarifs horaires par niveau</h1>

      {isLoading && <p className="text-sm text-zinc-500">Chargement…</p>}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {niveaux?.map((n) => (
          <TarifCard key={n.id} niveau={n} />
        ))}
      </div>
    </div>
  );
}

function TarifCard({ niveau }: { niveau: NiveauAvecTarif }) {
  const [value, setValue] = useState(niveau.tarif_heure?.tarif_heure ?? "0");
  const queryClient = useQueryClient();

  const update = useMutation({
    mutationFn: () =>
      apiFetch(`/api/tarifs-heures/${niveau.id}`, {
        method: "PUT",
        body: JSON.stringify({ tarif_heure: value }),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["tarifs-heures"] }),
  });

  return (
    <Card>
      <CardContent className="flex flex-col gap-2 py-4">
        <p className="font-medium">{niveau.nom}</p>
        <div className="flex gap-2">
          <Input
            type="number"
            min={0}
            step="0.01"
            value={value}
            onChange={(e) => setValue(e.target.value)}
          />
          <Button size="sm" onClick={() => update.mutate()} disabled={update.isPending}>
            {update.isPending ? "…" : "Enregistrer"}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
