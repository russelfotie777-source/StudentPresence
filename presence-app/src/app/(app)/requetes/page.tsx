"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";
import { AlertCircle, Paperclip } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { useMyRequetes, useSubmitRequete } from "@/hooks/use-requetes";
import { ApiError } from "@/lib/api-client";
import type { RequestStatus } from "@/types/api";
import { cn } from "@/lib/utils";

const STATUS_LABELS: Record<RequestStatus, { label: string; className: string }> = {
  en_attente: { label: "En attente", className: "bg-warning/20 text-warning-foreground" },
  acceptee: { label: "Acceptée", className: "bg-success/15 text-success" },
  rejetee: { label: "Rejetée", className: "bg-destructive/10 text-destructive" },
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
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">Mes requêtes</h1>

      <form
        onSubmit={handleSubmit}
        className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4"
      >
        <p className="text-sm font-medium text-foreground">Contester une séance</p>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="seance_id">N° de séance</Label>
          <Input
            id="seance_id"
            type="number"
            required
            value={seanceId}
            onChange={(e) => setSeanceId(e.target.value)}
            className="h-11 rounded-xl"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="description">Description</Label>
          <textarea
            id="description"
            required
            rows={3}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            className="rounded-xl border border-input bg-transparent px-3 py-2.5 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="preuve">Preuve (image ou PDF, 5 Mo max)</Label>
          <label
            htmlFor="preuve"
            className="flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-dashed border-input px-3 text-sm text-muted-foreground"
          >
            <Paperclip className="h-4 w-4" />
            {file ? file.name : "Choisir un fichier"}
          </label>
          <input
            id="preuve"
            type="file"
            required
            accept="image/*,.pdf"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="hidden"
          />
        </div>
        {errorMessage && (
          <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}
        <Button type="submit" disabled={submit.isPending} className="h-11 rounded-xl">
          {submit.isPending ? "Envoi…" : "Envoyer la requête"}
        </Button>
      </form>

      <div className="flex flex-col gap-2.5">
        {isLoading && <p className="text-sm text-muted-foreground">Chargement…</p>}
        {requetes?.map((r) => (
          <div key={r.id} className="flex flex-col gap-1.5 rounded-2xl border border-border bg-card p-4">
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm font-semibold text-foreground">
                {r.matiere} — {r.salle}
              </p>
              <span
                className={cn(
                  "shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium",
                  STATUS_LABELS[r.statut].className,
                )}
              >
                {STATUS_LABELS[r.statut].label}
              </span>
            </div>
            <p className="text-xs text-muted-foreground">{r.description}</p>
            {r.commentaire_admin && (
              <p className="text-xs italic text-muted-foreground/80">
                Réponse admin : {r.commentaire_admin}
              </p>
            )}
          </div>
        ))}
        {requetes?.length === 0 && (
          <p className="text-center text-sm text-muted-foreground">Aucune requête pour l&apos;instant.</p>
        )}
      </div>
    </div>
  );
}
