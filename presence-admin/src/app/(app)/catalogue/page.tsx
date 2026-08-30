"use client";

import { useState } from "react";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  filiereHooks,
  matiereHooks,
  niveauHooks,
  salleHooks,
  type Filiere,
} from "@/hooks/use-catalog";

type Tab = "niveaux" | "filieres" | "salles" | "matieres";

export default function CataloguePage() {
  const [tab, setTab] = useState<Tab>("niveaux");

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold text-zinc-900">Catalogue académique</h1>

      <Tabs value={tab} onValueChange={(v) => setTab(v as Tab)}>
        <TabsList>
          <TabsTrigger value="niveaux">Niveaux</TabsTrigger>
          <TabsTrigger value="filieres">Filières</TabsTrigger>
          <TabsTrigger value="salles">Salles</TabsTrigger>
          <TabsTrigger value="matieres">Matières</TabsTrigger>
        </TabsList>
      </Tabs>

      {tab === "niveaux" && <NiveauxPanel />}
      {tab === "filieres" && <FilieresPanel />}
      {tab === "salles" && <SallesPanel />}
      {tab === "matieres" && <MatieresPanel />}
    </div>
  );
}

function NiveauxPanel() {
  const { data: niveaux } = niveauHooks.useList();
  const create = niveauHooks.useCreate();
  const remove = niveauHooks.useRemove();
  const [nom, setNom] = useState("");

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 py-4">
        <div className="flex gap-2">
          <Input placeholder="Ex : L3" value={nom} onChange={(e) => setNom(e.target.value)} />
          <Button
            onClick={() => create.mutate({ nom }, { onSuccess: () => setNom("") })}
            disabled={!nom || create.isPending}
          >
            Ajouter
          </Button>
        </div>
        <ul className="flex flex-col divide-y">
          {niveaux?.map((n) => (
            <li key={n.id} className="flex items-center justify-between py-2 text-sm">
              {n.nom}
              <Button variant="ghost" size="sm" onClick={() => remove.mutate(n.id)}>
                Supprimer
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

function FilieresPanel() {
  const { data: niveaux } = niveauHooks.useList();
  const [niveauId, setNiveauId] = useState<string>("");
  const { data: filieres } = filiereHooks.useList(niveauId ? `?niveau_id=${niveauId}` : "");
  const create = filiereHooks.useCreate();
  const remove = filiereHooks.useRemove();
  const [nom, setNom] = useState("");

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 py-4">
        <Select value={niveauId} onValueChange={setNiveauId}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Filtrer par niveau" />
          </SelectTrigger>
          <SelectContent>
            {niveaux?.map((n) => (
              <SelectItem key={n.id} value={String(n.id)}>
                {n.nom}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <div className="flex gap-2">
          <Input
            placeholder="Nom de la filière"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
          />
          <Button
            onClick={() =>
              create.mutate(
                { nom, niveau_id: Number(niveauId) },
                { onSuccess: () => setNom("") },
              )
            }
            disabled={!nom || !niveauId || create.isPending}
          >
            Ajouter
          </Button>
        </div>

        <ul className="flex flex-col divide-y">
          {filieres?.map((f: Filiere) => (
            <li key={f.id} className="flex items-center justify-between py-2 text-sm">
              {f.nom}
              <Button variant="ghost" size="sm" onClick={() => remove.mutate(f.id)}>
                Supprimer
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

function SallesPanel() {
  const { data: filieres } = filiereHooks.useList();
  const [filiereId, setFiliereId] = useState<string>("");
  const { data: salles } = salleHooks.useList(filiereId ? `?filiere_id=${filiereId}` : "");
  const create = salleHooks.useCreate();
  const remove = salleHooks.useRemove();
  const [nom, setNom] = useState("");
  const [formation, setFormation] = useState("FI");

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 py-4">
        <Select value={filiereId} onValueChange={setFiliereId}>
          <SelectTrigger className="w-56">
            <SelectValue placeholder="Filtrer par filière" />
          </SelectTrigger>
          <SelectContent>
            {filieres?.map((f) => (
              <SelectItem key={f.id} value={String(f.id)}>
                {f.nom}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <div className="flex flex-wrap gap-2">
          <Input placeholder="Nom de la salle" value={nom} onChange={(e) => setNom(e.target.value)} />
          <Select value={formation} onValueChange={setFormation}>
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="FI">FI</SelectItem>
              <SelectItem value="FA">FA</SelectItem>
            </SelectContent>
          </Select>
          <Button
            onClick={() =>
              create.mutate(
                { nom, filiere_id: Number(filiereId), formation },
                { onSuccess: () => setNom("") },
              )
            }
            disabled={!nom || !filiereId || create.isPending}
          >
            Ajouter
          </Button>
        </div>

        <ul className="flex flex-col divide-y">
          {salles?.map((s) => (
            <li key={s.id} className="flex items-center justify-between py-2 text-sm">
              {s.nom} ({s.formation})
              <Button variant="ghost" size="sm" onClick={() => remove.mutate(s.id)}>
                Supprimer
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

function MatieresPanel() {
  const { data: matieres } = matiereHooks.useList();
  const create = matiereHooks.useCreate();
  const remove = matiereHooks.useRemove();
  const [nom, setNom] = useState("");
  const [code, setCode] = useState("");

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 py-4">
        <div className="flex flex-wrap gap-2">
          <Input placeholder="Nom" value={nom} onChange={(e) => setNom(e.target.value)} />
          <Input placeholder="Code" value={code} onChange={(e) => setCode(e.target.value)} className="w-32" />
          <Button
            onClick={() =>
              create.mutate({ nom, code }, { onSuccess: () => { setNom(""); setCode(""); } })
            }
            disabled={!nom || !code || create.isPending}
          >
            Ajouter
          </Button>
        </div>
        <ul className="flex flex-col divide-y">
          {matieres?.map((m) => (
            <li key={m.id} className="flex items-center justify-between py-2 text-sm">
              {m.nom} ({m.code})
              <Button variant="ghost" size="sm" onClick={() => remove.mutate(m.id)}>
                Supprimer
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}
