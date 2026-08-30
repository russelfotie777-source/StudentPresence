"use client";

import { useState } from "react";
import { Search, UserPlus2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AlertCircle } from "lucide-react";
import { useActivePromotions, useCreatePromotion, useStudentSearch } from "@/hooks/use-promotions";
import { ApiError } from "@/lib/api-client";

export default function PromotionPage() {
  const [search, setSearch] = useState("");
  const [duree, setDuree] = useState(60);
  const { data: students } = useStudentSearch(search);
  const { data: actives } = useActivePromotions();
  const createPromotion = useCreatePromotion();

  const errorMessage =
    createPromotion.error instanceof ApiError
      ? (createPromotion.error.errors?.etudiant_id?.[0] ?? createPromotion.error.message)
      : null;

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Promotion temporaire
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Donnez temporairement les droits de délégué à un étudiant.
        </p>
      </div>

      <div className="flex gap-2">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Rechercher un étudiant…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="h-11 rounded-xl pl-10"
          />
        </div>
        <Input
          type="number"
          min={1}
          max={1440}
          value={duree}
          onChange={(e) => setDuree(Number(e.target.value))}
          className="h-11 w-20 rounded-xl text-center"
          title="Durée en minutes"
        />
      </div>

      {errorMessage && (
        <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      <div className="flex flex-col gap-2.5">
        {students?.map((s) => (
          <div
            key={s.id}
            className="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card p-4"
          >
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-foreground">{s.name}</p>
              <p className="truncate text-xs text-muted-foreground">
                {s.salle} · {s.filiere} · {s.niveau}
              </p>
            </div>
            {s.has_active_promotion ? (
              <span className="shrink-0 rounded-full bg-secondary px-2.5 py-1 text-[11px] font-medium text-secondary-foreground">
                Déjà promu
              </span>
            ) : (
              <Button
                size="sm"
                className="h-9 shrink-0 gap-1.5 rounded-lg"
                disabled={createPromotion.isPending}
                onClick={() =>
                  createPromotion.mutate({ etudiant_id: s.id, duree_minutes: duree })
                }
              >
                <UserPlus2 className="h-3.5 w-3.5" />
                Promouvoir
              </Button>
            )}
          </div>
        ))}
      </div>

      {actives && actives.length > 0 && (
        <div className="flex flex-col gap-2.5">
          <h2 className="text-sm font-semibold text-foreground">Promotions actives</h2>
          {actives.map((p) => (
            <div
              key={p.id}
              className="flex items-center justify-between rounded-2xl border border-border bg-card p-4 text-sm"
            >
              <span className="font-medium text-foreground">
                {p.etudiant.name} <span className="font-normal text-muted-foreground">— {p.etudiant.salle?.nom}</span>
              </span>
              <span className="text-xs text-muted-foreground">
                jusqu&apos;à{" "}
                {new Date(p.date_fin).toLocaleTimeString("fr-FR", {
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
