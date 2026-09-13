"use client";

import { useState } from "react";
import { FileDown } from "lucide-react";
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
import type { Semaine } from "@/hooks/use-catalog";
import { useTelechargerListe } from "@/hooks/use-etudiants";
import type { SalleFeuille, Symboles } from "@/hooks/use-feuille-presence";
import { plageSemaine } from "@/lib/dates";
import { SelecteurSymboles } from "./selecteur-symboles";

interface Props {
  salle: SalleFeuille;
  semaine: Semaine;
  symboles: Symboles;
  onFermer: () => void;
}

/**
 * Le PDF officiel de la salle et de la semaine affichées — exactement ce
 * que la grille montre, dans la notation choisie. Semestre et année sont
 * déduits ; on ne les demande que s'ils diffèrent.
 */
export function DialogueListe({ salle, semaine, symboles, onFermer }: Props) {
  const telecharger = useTelechargerListe();
  const [semestre, setSemestre] = useState("");
  const [annee, setAnnee] = useState("");

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <FileDown className="size-4 text-primary" />
            Liste de présence officielle
          </DialogTitle>
          <DialogDescription>
            Salle <span className="font-medium text-foreground">{salle.nom}</span>, semaine{" "}
            <span className="font-medium text-foreground">
              S{semaine.numero} · {plageSemaine(semaine.date_debut, semaine.date_fin)}
            </span>
            . Format du département, en-tête bilingue, une colonne par jour avec les présences
            relevées, séances de la semaine préremplies, étudiants FM signalés.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs text-muted-foreground">Notation des présences</Label>
            <SelecteurSymboles valeur={symboles} />
            <p className="text-[12px] text-muted-foreground">
              Réglage commun à la grille et à toutes les listes générées.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="semestre" className="text-xs text-muted-foreground">
                Semestre (optionnel)
              </Label>
              <Input
                id="semestre"
                type="number"
                min={1}
                max={6}
                value={semestre}
                onChange={(e) => setSemestre(e.target.value)}
                placeholder="déduit du niveau et de la date"
                className="h-10 rounded-lg"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="annee" className="text-xs text-muted-foreground">
                Année académique (optionnel)
              </Label>
              <Input
                id="annee"
                value={annee}
                onChange={(e) => setAnnee(e.target.value)}
                placeholder="ex. 2026-2027"
                className="h-10 rounded-lg"
              />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onFermer}>
            Fermer
          </Button>
          <Button
            className="gap-1.5"
            disabled={telecharger.isPending}
            onClick={() =>
              telecharger.mutate({
                salleId: salle.id,
                semaineId: semaine.id,
                semestre: semestre ? Number(semestre) : undefined,
                annee: annee || undefined,
                symboles,
              })
            }
          >
            <FileDown className="size-4" />
            {telecharger.isPending ? "Génération…" : "Télécharger le PDF"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
