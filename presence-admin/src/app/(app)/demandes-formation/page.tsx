"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  useApproveFormationRequest,
  useFormationRequests,
  useRejectFormationRequest,
} from "@/hooks/use-formation-requests";
import { salleHooks } from "@/hooks/use-catalog";
import type { RequestStatus } from "@/types/api";

const STATUS_LABELS: Record<RequestStatus, { label: string; className: string }> = {
  en_attente: { label: "En attente", className: "bg-amber-100 text-amber-800" },
  acceptee: { label: "Acceptée", className: "bg-green-100 text-green-800" },
  rejetee: { label: "Rejetée", className: "bg-red-100 text-red-800" },
};

export default function DemandesFormationPage() {
  const [tab, setTab] = useState<RequestStatus>("en_attente");
  const { data: demandes, isLoading } = useFormationRequests(tab);
  const { data: salles } = salleHooks.useList();
  const sallesFI = salles?.filter((s) => s.formation === "FI") ?? [];

  const approve = useApproveFormationRequest();
  const reject = useRejectFormationRequest();
  const [salleChoisie, setSalleChoisie] = useState<Record<number, string>>({});
  const [comments, setComments] = useState<Record<number, string>>({});

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold text-zinc-900">Demandes de migration FA → FI</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Un étudiant en Formation Alternance (FA) qui demande à suivre l&apos;emploi du temps
          Formation Initiale (FI). L&apos;approbation le bascule en FM et le rattache à la salle FI
          choisie ci-dessous.
        </p>
      </div>

      <Tabs value={tab} onValueChange={(v) => setTab(v as RequestStatus)}>
        <TabsList>
          <TabsTrigger value="en_attente">En attente</TabsTrigger>
          <TabsTrigger value="acceptee">Acceptées</TabsTrigger>
          <TabsTrigger value="rejetee">Rejetées</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading && <p className="text-sm text-zinc-500">Chargement…</p>}

      <div className="flex flex-col gap-3">
        {demandes?.map((d) => (
          <Card key={d.id}>
            <CardContent className="flex flex-col gap-2 py-4">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-medium">
                    {d.etudiant?.name} — {d.etudiant?.phone}
                  </p>
                  <p className="text-sm text-zinc-500">Salle FA actuelle : {d.etudiant?.salle}</p>
                  {d.motif && <p className="mt-1 text-sm text-zinc-600">« {d.motif} »</p>}
                  {d.salle_cible && (
                    <p className="mt-1 text-sm text-zinc-500">
                      Basculé vers : <span className="font-medium">{d.salle_cible.nom}</span>
                    </p>
                  )}
                </div>
                <Badge className={STATUS_LABELS[d.statut].className}>
                  {STATUS_LABELS[d.statut].label}
                </Badge>
              </div>

              {d.statut === "en_attente" && (
                <div className="flex flex-col gap-2 border-t pt-2">
                  <div className="flex items-center gap-2">
                    <Select
                      value={salleChoisie[d.id] ?? ""}
                      onValueChange={(v) => setSalleChoisie((c) => ({ ...c, [d.id]: v ?? "" }))}
                    >
                      <SelectTrigger className="w-56">
                        <SelectValue placeholder="Salle FI cible…" />
                      </SelectTrigger>
                      <SelectContent>
                        {sallesFI.map((s) => (
                          <SelectItem key={s.id} value={String(s.id)}>
                            {s.nom}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <Button
                      size="sm"
                      disabled={!salleChoisie[d.id] || approve.isPending}
                      onClick={() =>
                        approve.mutate({ id: d.id, salle_id: Number(salleChoisie[d.id]) })
                      }
                    >
                      Approuver
                    </Button>
                  </div>
                  <Textarea
                    placeholder="Motif de rejet (optionnel)"
                    value={comments[d.id] ?? ""}
                    onChange={(e) => setComments((c) => ({ ...c, [d.id]: e.target.value }))}
                    rows={2}
                  />
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={reject.isPending}
                    onClick={() => reject.mutate({ id: d.id, commentaire: comments[d.id] })}
                  >
                    Rejeter
                  </Button>
                </div>
              )}
              {d.commentaire_admin && (
                <p className="text-xs italic text-zinc-500">Note : {d.commentaire_admin}</p>
              )}
            </CardContent>
          </Card>
        ))}
        {demandes?.length === 0 && (
          <p className="text-sm text-zinc-400">Aucune demande dans cette catégorie.</p>
        )}
      </div>
    </div>
  );
}
