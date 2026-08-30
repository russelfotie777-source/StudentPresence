"use client";

import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { matiereHooks, salleHooks, semaineHooks } from "@/hooks/use-catalog";
import {
  courseTemplateHooks,
  useEnseignants,
  useGenerateSeances,
  useGenerateSemester,
} from "@/hooks/use-scheduling";
import type { Weekday } from "@/types/api";

const JOURS: Weekday[] = ["LUNDI", "MARDI", "MERCREDI", "JEUDI", "VENDREDI", "SAMEDI", "DIMANCHE"];

export default function EmploisDuTempsPage() {
  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold text-zinc-900">Emplois du temps</h1>
      <SemainesSection />
      <CourseTemplatesSection />
    </div>
  );
}

function SemainesSection() {
  const { data: semaines } = semaineHooks.useList();
  const generateSemester = useGenerateSemester();
  const [dateDebut, setDateDebut] = useState("");
  const [nombre, setNombre] = useState(12);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Semaines du semestre</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        <div className="flex flex-wrap items-end gap-2">
          <div className="flex flex-col gap-1">
            <Label htmlFor="date-debut">1er lundi du semestre</Label>
            <Input
              id="date-debut"
              type="date"
              value={dateDebut}
              onChange={(e) => setDateDebut(e.target.value)}
            />
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="nombre">Nombre de semaines</Label>
            <Input
              id="nombre"
              type="number"
              min={1}
              max={52}
              value={nombre}
              onChange={(e) => setNombre(Number(e.target.value))}
              className="w-24"
            />
          </div>
          <Button
            onClick={() => generateSemester.mutate({ date_debut: dateDebut, nombre_semaines: nombre })}
            disabled={!dateDebut || generateSemester.isPending}
          >
            {generateSemester.isPending ? "Génération…" : "Générer les semaines"}
          </Button>
        </div>

        <div className="flex flex-wrap gap-2">
          {semaines?.map((s) => (
            <Badge key={s.id} variant="outline">
              S{s.numero} · {s.date_debut}
            </Badge>
          ))}
          {semaines?.length === 0 && (
            <p className="text-sm text-zinc-400">Aucune semaine créée pour l&apos;instant.</p>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

function CourseTemplatesSection() {
  const { data: templates } = courseTemplateHooks.useList();
  const { data: matieres } = matiereHooks.useList();
  const { data: salles } = salleHooks.useList();
  const { data: enseignants } = useEnseignants();
  const create = courseTemplateHooks.useCreate();
  const generate = useGenerateSeances();
  const [results, setResults] = useState<Record<number, string>>({});

  const [form, setForm] = useState({
    matiere_id: "",
    enseignant_id: "",
    salle_id: "",
    groupe: "G1",
    jour: "LUNDI" as Weekday,
    heure_debut: "08:00",
    heure_fin: "10:00",
    date_debut: "",
    date_fin: "",
  });

  function submit() {
    create.mutate({
      ...form,
      matiere_id: Number(form.matiere_id),
      enseignant_id: Number(form.enseignant_id),
      salle_id: Number(form.salle_id),
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Cours récurrents</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
          <Select value={form.matiere_id} onValueChange={(v) => setForm((f) => ({ ...f, matiere_id: v ?? "" }))}>
            <SelectTrigger><SelectValue placeholder="Matière" /></SelectTrigger>
            <SelectContent>
              {matieres?.map((m) => <SelectItem key={m.id} value={String(m.id)}>{m.nom}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select value={form.enseignant_id} onValueChange={(v) => setForm((f) => ({ ...f, enseignant_id: v ?? "" }))}>
            <SelectTrigger><SelectValue placeholder="Enseignant" /></SelectTrigger>
            <SelectContent>
              {enseignants?.map((e) => <SelectItem key={e.id} value={String(e.id)}>{e.name}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select value={form.salle_id} onValueChange={(v) => setForm((f) => ({ ...f, salle_id: v ?? "" }))}>
            <SelectTrigger><SelectValue placeholder="Salle" /></SelectTrigger>
            <SelectContent>
              {salles?.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.nom}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select value={form.jour} onValueChange={(v) => setForm((f) => ({ ...f, jour: (v ?? "LUNDI") as Weekday }))}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              {JOURS.map((j) => <SelectItem key={j} value={j}>{j}</SelectItem>)}
            </SelectContent>
          </Select>

          <Input
            type="time"
            value={form.heure_debut}
            onChange={(e) => setForm((f) => ({ ...f, heure_debut: e.target.value }))}
          />
          <Input
            type="time"
            value={form.heure_fin}
            onChange={(e) => setForm((f) => ({ ...f, heure_fin: e.target.value }))}
          />
          <Input
            type="date"
            value={form.date_debut}
            onChange={(e) => setForm((f) => ({ ...f, date_debut: e.target.value }))}
            title="Date de début de validité"
          />
          <Input
            type="date"
            value={form.date_fin}
            onChange={(e) => setForm((f) => ({ ...f, date_fin: e.target.value }))}
            title="Date de fin de validité"
          />
        </div>

        {create.error && (
          <Alert variant="destructive">
            <AlertDescription>{create.error.message}</AlertDescription>
          </Alert>
        )}

        <Button
          onClick={submit}
          disabled={create.isPending}
          className="w-fit"
        >
          Créer le cours récurrent
        </Button>

        <div className="flex flex-col divide-y">
          {templates?.map((t) => (
            <div key={t.id} className="flex items-center justify-between py-2 text-sm">
              <div>
                <p className="font-medium">
                  {t.matiere?.nom} — {t.enseignant?.name} — {t.salle?.nom}
                </p>
                <p className="text-xs text-zinc-500">
                  {t.jour} {t.heure_debut}–{t.heure_fin} · {t.date_debut} → {t.date_fin}
                </p>
                {results[t.id] && <p className="text-xs text-primary">{results[t.id]}</p>}
              </div>
              <Button
                size="sm"
                variant="secondary"
                disabled={generate.isPending}
                onClick={() =>
                  generate.mutate(t.id, {
                    onSuccess: (result) =>
                      setResults((r) => ({
                        ...r,
                        [t.id]: `${result.created.length} séance(s) créée(s), ${result.skipped.length} ignorée(s)`,
                      })),
                  })
                }
              >
                Générer les séances
              </Button>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
