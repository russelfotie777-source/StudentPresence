"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { Semaine } from "@/hooks/use-catalog";
import { plageSemaine, type Ymd } from "@/lib/dates";

interface Props {
  semaines: Semaine[];
  semaine: Semaine | null;
  /** Date du jour à Douala, pour repérer « cette semaine » et le bouton Aujourd'hui. */
  aujourdhui: Ymd | null;
  onChoisir: (semaineId: number) => void;
}

/** La semaine qui couvre une date, parmi celles du semestre. */
export function semaineCouvrant(semaines: Semaine[], date: Ymd | null): Semaine | null {
  if (!date) return null;
  return semaines.find((s) => s.date_debut <= date && date <= s.date_fin) ?? null;
}

/**
 * ‹ S37 · 7 → 13 sept. › Aujourd'hui — le même navigateur pour l'emploi du
 * temps et les présences : on se déplace de semaine en semaine sans lâcher
 * la souris, et un clic ramène à la semaine en cours.
 */
export function NavigateurSemaine({ semaines, semaine, aujourdhui, onChoisir }: Props) {
  const index = semaine ? semaines.findIndex((s) => s.id === semaine.id) : -1;
  const precedente = index > 0 ? semaines[index - 1] : null;
  const suivante = index >= 0 && index < semaines.length - 1 ? semaines[index + 1] : null;
  const semaineDuJour = semaineCouvrant(semaines, aujourdhui);

  return (
    <div className="flex items-center gap-1.5">
      <Button
        size="icon"
        variant="outline"
        aria-label="Semaine précédente"
        disabled={!precedente}
        onClick={() => precedente && onChoisir(precedente.id)}
      >
        <ChevronLeft className="size-4" />
      </Button>
      <Select value={semaine ? String(semaine.id) : ""} onValueChange={(v) => v && onChoisir(Number(v))}>
        <SelectTrigger className="h-8 w-52 rounded-lg font-medium">
          <SelectValue placeholder="Semaine…">
            {() => semaine && `S${semaine.numero} · ${plageSemaine(semaine.date_debut, semaine.date_fin)}`}
          </SelectValue>
        </SelectTrigger>
        <SelectContent>
          {semaines.map((s) => (
            <SelectItem key={s.id} value={String(s.id)}>
              S{s.numero} · {plageSemaine(s.date_debut, s.date_fin)}
              {s.id === semaineDuJour?.id ? " · cette semaine" : ""}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Button
        size="icon"
        variant="outline"
        aria-label="Semaine suivante"
        disabled={!suivante}
        onClick={() => suivante && onChoisir(suivante.id)}
      >
        <ChevronRight className="size-4" />
      </Button>
      <Button
        variant="ghost"
        size="sm"
        disabled={!semaineDuJour || semaineDuJour.id === semaine?.id}
        onClick={() => semaineDuJour && onChoisir(semaineDuJour.id)}
      >
        Aujourd&apos;hui
      </Button>
    </div>
  );
}
