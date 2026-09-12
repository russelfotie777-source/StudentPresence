"use client";

import { useState } from "react";
import { toast } from "sonner";
import { AlertCircle, CalendarCheck2, CalendarClock, Repeat } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import type { Matiere, Salle, Semaine } from "@/hooks/use-catalog";
import type { Enseignant } from "@/hooks/use-scheduling";
import {
  useProgrammerCours,
  type ResultatProgrammation,
} from "@/hooks/use-emploi-du-temps";
import { ApiError } from "@/lib/api-client";
import { libelleSalle } from "@/lib/catalogue";
import { dateLocale, formaterYmd, plageSemaine, type Ymd } from "@/lib/dates";
import type { Weekday } from "@/types/api";
import { JOURS, jourDepuisDate } from "./constantes";

export interface PreremplissageCours {
  salle_id?: number;
  enseignant_id?: number;
  jour?: Weekday;
  date?: Ymd;
  heure_debut?: string;
  heure_fin?: string;
}

interface Props {
  prerempli: PreremplissageCours;
  salles: Salle[];
  matieres: Matiere[];
  enseignants: Enseignant[];
  semaines: Semaine[];
  semaineCourante: Semaine | null;
  onFermer: () => void;
}

type Recurrence = "hebdo" | "ponctuel";

function plusDeuxHeures(heure: string): string {
  const [h, m] = heure.split(":").map(Number);
  return `${String(Math.min(h + 2, 23)).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

/**
 * Programmer un cours = le créer et poser ses séances dans la foulée. Deux
 * cas seulement : chaque semaine (de la semaine X à la semaine Y du
 * semestre) ou une seule fois à une date. Les conflits éventuels sont
 * rendus semaine par semaine plutôt qu'avalés dans un toast.
 */
export function DialogueCours({
  prerempli,
  salles,
  matieres,
  enseignants,
  semaines,
  semaineCourante,
  onFermer,
}: Props) {
  const programmer = useProgrammerCours();
  const [resultat, setResultat] = useState<ResultatProgrammation | null>(null);

  const [recurrence, setRecurrence] = useState<Recurrence>("hebdo");
  const [salleId, setSalleId] = useState(prerempli.salle_id ? String(prerempli.salle_id) : "");
  const [matiereId, setMatiereId] = useState("");
  const [enseignantId, setEnseignantId] = useState(
    prerempli.enseignant_id ? String(prerempli.enseignant_id) : "",
  );
  const [jour, setJour] = useState<Weekday>(prerempli.jour ?? "LUNDI");
  const [heureDebut, setHeureDebut] = useState(prerempli.heure_debut ?? "08:00");
  const [heureFin, setHeureFin] = useState(
    prerempli.heure_fin ?? plusDeuxHeures(prerempli.heure_debut ?? "08:00"),
  );
  const [semaineDeId, setSemaineDeId] = useState(
    String(semaineCourante?.id ?? semaines[0]?.id ?? ""),
  );
  const [semaineAId, setSemaineAId] = useState(String(semaines.at(-1)?.id ?? ""));
  const [date, setDate] = useState<Ymd>(prerempli.date ?? semaineCourante?.date_debut ?? "");

  const semaineDe = semaines.find((s) => String(s.id) === semaineDeId);
  const semaineA = semaines.find((s) => String(s.id) === semaineAId);
  const ordreOk = !semaineDe || !semaineA || semaineDe.numero <= semaineA.numero;

  const pret =
    salleId &&
    matiereId &&
    enseignantId &&
    heureDebut < heureFin &&
    (recurrence === "hebdo" ? semaineDe && semaineA && ordreOk : Boolean(date));

  const erreur =
    programmer.error instanceof ApiError
      ? Object.values(programmer.error.errors ?? {}).flat()[0] ?? programmer.error.message
      : programmer.error
        ? "La programmation a échoué."
        : null;

  function soumettre() {
    if (!pret) return;
    const ponctuel = recurrence === "ponctuel";

    programmer.mutate(
      {
        matiere_id: Number(matiereId),
        enseignant_id: Number(enseignantId),
        salle_id: Number(salleId),
        jour: ponctuel ? jourDepuisDate(dateLocale(date)) : jour,
        heure_debut: heureDebut,
        heure_fin: heureFin,
        date_debut: ponctuel ? date : semaineDe!.date_debut,
        date_fin: ponctuel ? date : semaineA!.date_fin,
      },
      {
        onSuccess: (r) => {
          if (r.skipped.length === 0) {
            toast.success(
              r.created.length === 1
                ? "Séance programmée."
                : `${r.created.length} séances programmées.`,
            );
            onFermer();
          } else {
            setResultat(r);
          }
        },
      },
    );
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent className="sm:max-w-lg">
        {resultat ? (
          <>
            <DialogHeader>
              <DialogTitle>Programmation partielle</DialogTitle>
              <DialogDescription>
                {resultat.created.length === 0
                  ? "Aucune séance n'a pu être programmée."
                  : `${resultat.created.length} séance(s) programmée(s), mais ${resultat.skipped.length} semaine(s) n'ont pas pu l'être :`}
              </DialogDescription>
            </DialogHeader>
            <ul className="flex max-h-64 flex-col gap-1.5 overflow-y-auto text-[13px]">
              {resultat.skipped.map((s) => (
                <li
                  key={s.semaine_id}
                  className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2"
                >
                  <AlertCircle className="mt-0.5 size-3.5 shrink-0 text-warning-foreground" />
                  <span>
                    <span className="font-medium">
                      S{s.numero} · {formaterYmd(s.date, { day: "numeric", month: "short" })}
                    </span>{" "}
                    — {s.reason}
                  </span>
                </li>
              ))}
            </ul>
            <DialogFooter>
              <Button onClick={onFermer}>Compris</Button>
            </DialogFooter>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle>Programmer un cours</DialogTitle>
              <DialogDescription>
                Les étudiants de la salle verront chaque séance dans leur application le jour
                même, à l&apos;heure de Douala.
              </DialogDescription>
            </DialogHeader>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Champ label="Salle" large>
                <Selecteur
                  valeur={salleId}
                  onChange={setSalleId}
                  placeholder="Choisir une salle…"
                  options={salles.map((s) => ({ valeur: String(s.id), label: libelleSalle(s) }))}
                />
              </Champ>
              <Champ label="Matière">
                <Selecteur
                  valeur={matiereId}
                  onChange={setMatiereId}
                  placeholder="Choisir…"
                  options={matieres.map((m) => ({ valeur: String(m.id), label: `${m.nom} (${m.code})` }))}
                />
              </Champ>
              <Champ label="Enseignant">
                <Selecteur
                  valeur={enseignantId}
                  onChange={setEnseignantId}
                  placeholder="Choisir…"
                  vide="Aucun enseignant validé."
                  options={enseignants.map((e) => ({ valeur: String(e.id), label: e.name }))}
                />
              </Champ>

              <div className="sm:col-span-2">
                <Tabs value={recurrence} onValueChange={(v) => setRecurrence(v as Recurrence)}>
                  <TabsList className="grid w-full grid-cols-2">
                    <TabsTrigger value="hebdo">
                      <Repeat className="size-3.5" /> Chaque semaine
                    </TabsTrigger>
                    <TabsTrigger value="ponctuel">
                      <CalendarClock className="size-3.5" /> Une seule fois
                    </TabsTrigger>
                  </TabsList>
                </Tabs>
              </div>

              {recurrence === "hebdo" ? (
                <>
                  <Champ label="Jour">
                    <Selecteur
                      valeur={jour}
                      onChange={(v) => setJour(v as Weekday)}
                      options={JOURS.map((j) => ({ valeur: j.valeur, label: j.long }))}
                    />
                  </Champ>
                  <div className="hidden sm:block" />
                  <Champ label="De la semaine">
                    <Selecteur
                      valeur={semaineDeId}
                      onChange={setSemaineDeId}
                      options={semaines.map((s) => ({
                        valeur: String(s.id),
                        label: `S${s.numero} · ${plageSemaine(s.date_debut, s.date_fin)}`,
                      }))}
                    />
                  </Champ>
                  <Champ label="À la semaine" erreur={!ordreOk ? "Doit suivre la semaine de début." : undefined}>
                    <Selecteur
                      valeur={semaineAId}
                      onChange={setSemaineAId}
                      options={semaines.map((s) => ({
                        valeur: String(s.id),
                        label: `S${s.numero} · ${plageSemaine(s.date_debut, s.date_fin)}`,
                      }))}
                    />
                  </Champ>
                </>
              ) : (
                <Champ label="Date" large>
                  <Input
                    type="date"
                    value={date}
                    min={semaines[0]?.date_debut}
                    max={semaines.at(-1)?.date_fin}
                    onChange={(e) => setDate(e.target.value)}
                    className="h-10 rounded-lg"
                  />
                </Champ>
              )}

              <Champ label="Début">
                <Input
                  type="time"
                  step={300}
                  value={heureDebut}
                  onChange={(e) => {
                    setHeureDebut(e.target.value);
                    if (e.target.value >= heureFin) setHeureFin(plusDeuxHeures(e.target.value));
                  }}
                  className="h-10 rounded-lg"
                />
              </Champ>
              <Champ label="Fin" erreur={heureFin <= heureDebut ? "Après l'heure de début." : undefined}>
                <Input
                  type="time"
                  step={300}
                  value={heureFin}
                  onChange={(e) => setHeureFin(e.target.value)}
                  className="h-10 rounded-lg"
                />
              </Champ>
            </div>

            {erreur && (
              <div className="flex items-start gap-2.5 rounded-xl border border-destructive/25 bg-destructive/10 px-3.5 py-3 text-[13px] text-destructive">
                <AlertCircle className="mt-0.5 size-4 shrink-0" />
                <span>{erreur}</span>
              </div>
            )}

            <DialogFooter>
              <Button variant="outline" onClick={onFermer}>
                Annuler
              </Button>
              <Button onClick={soumettre} disabled={!pret || programmer.isPending} className="gap-1.5">
                <CalendarCheck2 className="size-4" />
                {programmer.isPending ? "Programmation…" : "Programmer"}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

function Champ({
  label,
  large,
  erreur,
  children,
}: {
  label: string;
  large?: boolean;
  erreur?: string;
  children: React.ReactNode;
}) {
  return (
    <div className={large ? "flex flex-col gap-1.5 sm:col-span-2" : "flex flex-col gap-1.5"}>
      <Label className="text-xs text-muted-foreground">{label}</Label>
      {children}
      {erreur && <span className="text-[11.5px] text-destructive">{erreur}</span>}
    </div>
  );
}

export function Selecteur({
  valeur,
  onChange,
  options,
  placeholder,
  vide,
}: {
  valeur: string;
  onChange: (v: string) => void;
  options: { valeur: string; label: string }[];
  placeholder?: string;
  vide?: string;
}) {
  return (
    <Select value={valeur} onValueChange={(v) => onChange(v ?? "")}>
      <SelectTrigger className="h-10 w-full rounded-lg">
        {/* Sans fonction de rendu, le déclencheur afficherait la valeur brute. */}
        <SelectValue placeholder={placeholder}>
          {() => options.find((o) => o.valeur === valeur)?.label}
        </SelectValue>
      </SelectTrigger>
      <SelectContent>
        {options.length === 0 && vide && (
          <p className="px-2.5 py-2 text-xs text-muted-foreground">{vide}</p>
        )}
        {options.map((o) => (
          <SelectItem key={o.valeur} value={o.valeur}>
            {o.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
