"use client";

import { useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
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
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-zinc-900">Promotion temporaire</h1>
        <Link href="/dashboard" className="text-sm text-violet-700 underline">
          Retour
        </Link>
      </div>

      <p className="text-sm text-zinc-500">
        Donnez temporairement les droits de délégué à un étudiant (envoi de position, marquage
        présence, verrouillage du roster).
      </p>

      <div className="flex gap-2">
        <Input
          placeholder="Rechercher un étudiant…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <Input
          type="number"
          min={1}
          max={1440}
          value={duree}
          onChange={(e) => setDuree(Number(e.target.value))}
          className="w-24"
        />
      </div>
      <p className="-mt-2 text-xs text-zinc-400">Durée en minutes</p>

      {errorMessage && (
        <Alert variant="destructive">
          <AlertDescription>{errorMessage}</AlertDescription>
        </Alert>
      )}

      <div className="flex flex-col gap-2">
        {students?.map((s) => (
          <Card key={s.id}>
            <CardContent className="flex items-center justify-between py-3">
              <div>
                <p className="text-sm font-medium">{s.name}</p>
                <p className="text-xs text-zinc-500">
                  {s.salle} · {s.filiere} · {s.niveau}
                </p>
              </div>
              {s.has_active_promotion ? (
                <Badge variant="secondary">Déjà promu</Badge>
              ) : (
                <Button
                  size="sm"
                  disabled={createPromotion.isPending}
                  onClick={() =>
                    createPromotion.mutate({ etudiant_id: s.id, duree_minutes: duree })
                  }
                >
                  Promouvoir
                </Button>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      {actives && actives.length > 0 && (
        <>
          <h2 className="mt-2 text-sm font-semibold text-zinc-700">Promotions actives</h2>
          <div className="flex flex-col gap-2">
            {actives.map((p) => (
              <Card key={p.id}>
                <CardContent className="flex items-center justify-between py-3 text-sm">
                  <span>
                    {p.etudiant.name} — {p.etudiant.salle?.nom}
                  </span>
                  <span className="text-xs text-zinc-500">
                    jusqu&apos;à {new Date(p.date_fin).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}
                  </span>
                </CardContent>
              </Card>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
