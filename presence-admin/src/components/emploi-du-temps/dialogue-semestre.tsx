"use client";

import { useState } from "react";
import { toast } from "sonner";
import { CalendarPlus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { semaineHooks, type Semaine } from "@/hooks/use-catalog";
import { useGenerateSemester } from "@/hooks/use-scheduling";
import { ApiError } from "@/lib/api-client";
import { ajouterJours, formaterYmd, plageSemaine, type Ymd } from "@/lib/dates";
import { cn } from "@/lib/utils";

interface Props {
  semaines: Semaine[];
  semaineCouranteId: number | null;
  onFermer: () => void;
}

/**
 * Le calendrier du semestre : la liste des semaines numérotées sur
 * lesquelles les cours se posent. On les crée en bloc à partir du premier
 * lundi ; une semaine qui porte des séances ne se supprime pas.
 */
export function DialogueSemestre({ semaines, semaineCouranteId, onFermer }: Props) {
  const generer = useGenerateSemester();
  const supprimer = semaineHooks.useRemove();
  const [dateDebut, setDateDebut] = useState<Ymd>(() =>
    semaines.length ? ajouterJours(semaines[semaines.length - 1].date_fin, 1) : "",
  );
  const [nombre, setNombre] = useState(semaines.length ? 4 : 14);

  const premiere = semaines[0];
  const derniere = semaines.at(-1);

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Calendrier du semestre</DialogTitle>
          <DialogDescription>
            {premiere && derniere
              ? `${semaines.length} semaines, du ${formaterYmd(premiere.date_debut, { day: "numeric", month: "long" })} au ${formaterYmd(derniere.date_fin, { day: "numeric", month: "long", year: "numeric" })}.`
              : "Aucune semaine pour l'instant : les cours ne peuvent se poser que sur des semaines définies ici."}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-muted/40 p-3.5">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="semestre-debut" className="text-xs text-muted-foreground">
              {semaines.length ? "Ajouter à partir du lundi" : "Premier lundi du semestre"}
            </Label>
            <Input
              id="semestre-debut"
              type="date"
              value={dateDebut}
              onChange={(e) => setDateDebut(e.target.value)}
              className="h-10 rounded-lg"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="semestre-nombre" className="text-xs text-muted-foreground">
              Semaines
            </Label>
            <Input
              id="semestre-nombre"
              type="number"
              min={1}
              max={52}
              value={nombre}
              onChange={(e) => setNombre(Number(e.target.value))}
              className="h-10 w-20 rounded-lg"
            />
          </div>
          <Button
            className="h-10 gap-1.5"
            disabled={!dateDebut || nombre < 1 || generer.isPending}
            onClick={() =>
              generer.mutate(
                { date_debut: dateDebut, nombre_semaines: nombre },
                {
                  onSuccess: (r) => toast.success(`${r.length} semaine(s) ajoutée(s).`),
                  onError: (e) =>
                    toast.error(e instanceof ApiError ? e.message : "La génération a échoué."),
                },
              )
            }
          >
            <CalendarPlus className="size-4" />
            {generer.isPending ? "Création…" : "Créer"}
          </Button>
        </div>

        {semaines.length > 0 && (
          <ul className="flex max-h-72 flex-col divide-y divide-border overflow-y-auto rounded-xl border border-border">
            {semaines.map((s) => {
              const utilisee = (s.seances_count ?? 0) > 0;
              return (
                <li
                  key={s.id}
                  className={cn(
                    "flex items-center gap-3 px-3.5 py-2 text-sm",
                    s.id === semaineCouranteId && "bg-primary/5",
                  )}
                >
                  <span className="w-9 shrink-0 font-semibold tabular-nums text-foreground">S{s.numero}</span>
                  <span className="flex-1 text-muted-foreground">
                    {plageSemaine(s.date_debut, s.date_fin)}
                    {s.id === semaineCouranteId && (
                      <span className="ml-2 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10.5px] font-medium text-primary">
                        cette semaine
                      </span>
                    )}
                  </span>
                  <span className="text-xs tabular-nums text-muted-foreground">
                    {utilisee ? `${s.seances_count} séance(s)` : "—"}
                  </span>
                  <Button
                    size="icon-xs"
                    variant="ghost"
                    disabled={utilisee || supprimer.isPending}
                    title={utilisee ? "Annulez d'abord ses séances" : "Supprimer cette semaine"}
                    aria-label={`Supprimer la semaine ${s.numero}`}
                    className="text-muted-foreground hover:text-destructive"
                    onClick={() => supprimer.mutate(s.id)}
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                </li>
              );
            })}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  );
}
