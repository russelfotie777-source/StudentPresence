"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useAdminRequetes, useProcessRequete } from "@/hooks/use-admin-requetes";
import type { RequestStatus } from "@/types/api";

const STATUS_LABELS: Record<RequestStatus, { label: string; className: string }> = {
  en_attente: { label: "En attente", className: "bg-amber-100 text-amber-800" },
  acceptee: { label: "Acceptée", className: "bg-green-100 text-green-800" },
  rejetee: { label: "Rejetée", className: "bg-red-100 text-red-800" },
};

export default function AdminRequetesPage() {
  const [tab, setTab] = useState<RequestStatus>("en_attente");
  const { data: requetes, isLoading } = useAdminRequetes(tab);
  const process = useProcessRequete();
  const [comments, setComments] = useState<Record<number, string>>({});

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold text-zinc-900">Requêtes enseignants</h1>

      <Tabs value={tab} onValueChange={(v) => setTab(v as RequestStatus)}>
        <TabsList>
          <TabsTrigger value="en_attente">En attente</TabsTrigger>
          <TabsTrigger value="acceptee">Acceptées</TabsTrigger>
          <TabsTrigger value="rejetee">Rejetées</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading && <p className="text-sm text-zinc-500">Chargement…</p>}

      <div className="flex flex-col gap-3">
        {requetes?.map((r) => (
          <Card key={r.id}>
            <CardContent className="flex flex-col gap-2 py-4">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-medium">
                    {r.enseignant} — {r.matiere} ({r.salle})
                  </p>
                  <p className="text-sm text-zinc-500">{r.description}</p>
                  {r.preuve_url && (
                    <a
                      href={r.preuve_url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-sm text-violet-700 underline"
                    >
                      Voir la preuve
                    </a>
                  )}
                </div>
                <Badge className={STATUS_LABELS[r.statut].className}>
                  {STATUS_LABELS[r.statut].label}
                </Badge>
              </div>

              {r.statut === "en_attente" && (
                <div className="flex flex-col gap-2 border-t pt-2">
                  <Textarea
                    placeholder="Commentaire (optionnel)"
                    value={comments[r.id] ?? ""}
                    onChange={(e) => setComments((c) => ({ ...c, [r.id]: e.target.value }))}
                    rows={2}
                  />
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      onClick={() =>
                        process.mutate({ id: r.id, action: "acceptee", commentaire: comments[r.id] })
                      }
                      disabled={process.isPending}
                    >
                      Accepter
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() =>
                        process.mutate({ id: r.id, action: "rejetee", commentaire: comments[r.id] })
                      }
                      disabled={process.isPending}
                    >
                      Rejeter
                    </Button>
                  </div>
                </div>
              )}
              {r.commentaire_admin && (
                <p className="text-xs italic text-zinc-500">Note : {r.commentaire_admin}</p>
              )}
            </CardContent>
          </Card>
        ))}
        {requetes?.length === 0 && (
          <p className="text-sm text-zinc-400">Aucune requête dans cette catégorie.</p>
        )}
      </div>
    </div>
  );
}
