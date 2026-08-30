"use client";

import { Suspense, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useMyRequetes, useSubmitRequete } from "@/hooks/use-requetes";
import { ApiError } from "@/lib/api-client";
import type { RequestStatus } from "@/types/api";

const STATUS_LABELS: Record<RequestStatus, { label: string; className: string }> = {
  en_attente: { label: "En attente", className: "bg-amber-100 text-amber-800" },
  acceptee: { label: "Acceptée", className: "bg-green-100 text-green-800" },
  rejetee: { label: "Rejetée", className: "bg-red-100 text-red-800" },
};

export default function RequetesPage() {
  return (
    <Suspense fallback={null}>
      <RequetesContent />
    </Suspense>
  );
}

function RequetesContent() {
  const searchParams = useSearchParams();
  const preselectedSeanceId = searchParams.get("seance_id") ?? "";

  const { data: requetes, isLoading } = useMyRequetes();
  const submit = useSubmitRequete();

  const [seanceId, setSeanceId] = useState(preselectedSeanceId);
  const [description, setDescription] = useState("");
  const [file, setFile] = useState<File | null>(null);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!file) return;

    const form = new FormData();
    form.append("seance_id", seanceId);
    form.append("description", description);
    form.append("preuve", file);

    submit.mutate(form, {
      onSuccess: () => {
        setDescription("");
        setFile(null);
      },
    });
  }

  const errorMessage = submit.error instanceof ApiError ? submit.error.message : null;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-zinc-900">Mes requêtes</h1>
        <Link href="/dashboard" className="text-sm text-violet-700 underline">
          Retour
        </Link>
      </div>

      <Card>
        <CardContent className="py-4">
          <form onSubmit={handleSubmit} className="flex flex-col gap-3">
            <div className="flex flex-col gap-1">
              <Label htmlFor="seance_id">N° de séance</Label>
              <Input
                id="seance_id"
                type="number"
                required
                value={seanceId}
                onChange={(e) => setSeanceId(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="description">Description</Label>
              <textarea
                id="description"
                required
                rows={3}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="rounded-md border border-input px-3 py-2 text-sm"
              />
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="preuve">Preuve (image ou PDF, 5 Mo max)</Label>
              <input
                id="preuve"
                type="file"
                required
                accept="image/*,.pdf"
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                className="text-sm"
              />
            </div>
            {errorMessage && (
              <Alert variant="destructive">
                <AlertDescription>{errorMessage}</AlertDescription>
              </Alert>
            )}
            <Button type="submit" disabled={submit.isPending}>
              {submit.isPending ? "Envoi…" : "Envoyer la requête"}
            </Button>
          </form>
        </CardContent>
      </Card>

      <div className="flex flex-col gap-2">
        {isLoading && <p className="text-sm text-zinc-500">Chargement…</p>}
        {requetes?.map((r) => (
          <Card key={r.id}>
            <CardContent className="flex flex-col gap-1 py-3">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium">
                  {r.matiere} — {r.salle}
                </p>
                <Badge className={STATUS_LABELS[r.statut].className}>
                  {STATUS_LABELS[r.statut].label}
                </Badge>
              </div>
              <p className="text-xs text-zinc-500">{r.description}</p>
              {r.commentaire_admin && (
                <p className="text-xs italic text-zinc-600">
                  Réponse admin : {r.commentaire_admin}
                </p>
              )}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
