"use client";

import { useState } from "react";
import { Building2, FileDown } from "lucide-react";
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
import type { Departement, Semaine } from "@/hooks/use-catalog";
import { useTelechargerListe, useTelechargerListesDepartement } from "@/hooks/use-etudiants";
import type { SalleFeuille, Symboles } from "@/hooks/use-feuille-presence";
import { plageSemaine } from "@/lib/dates";
import { SelecteurSymboles } from "./selecteur-symboles";

/** Ce qu'on imprime : une salle, ou toutes les salles d'un département d'un coup. */
export type CibleListe =
  | { type: "salle"; salle: SalleFeuille }
  | { type: "departement"; departement: Departement; salles: number };

interface Props {
  cible: CibleListe;
  semaine: Semaine;
  symboles: Symboles;
  onFermer: () => void;
}

/**
 * Le PDF officiel de la semaine affichée — exactement ce que la grille
 * montre, dans la notation choisie, pour une salle ou pour tout un
 * département (une page par salle). Semestre et année sont déduits ; on
 * ne les demande que s'ils diffèrent.
 */
export function DialogueListe({ cible, semaine, symboles, onFermer }: Props) {
  const telechargerSalle = useTelechargerListe();
  const telechargerDepartement = useTelechargerListesDepartement();
  const [semestre, setSemestre] = useState("");
  const [annee, setAnnee] = useState("");

  const enCours = telechargerSalle.isPending || telechargerDepartement.isPending;
  const departement = cible.type === "departement";

  function telecharger() {
    const options = {
      semaineId: semaine.id,
      semestre: semestre ? Number(semestre) : undefined,
      annee: annee || undefined,
      symboles,
    };
    if (cible.type === "salle") telechargerSalle.mutate({ ...options, salleId: cible.salle.id });
    else telechargerDepartement.mutate({ ...options, departementId: cible.departement.id });
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onFermer()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {departement ? <Building2 className="size-4 text-primary" /> : <FileDown className="size-4 text-primary" />}
            {departement ? "Listes de présence du département" : "Liste de présence officielle"}
          </DialogTitle>
          <DialogDescription>
            {cible.type === "salle" ? (
              <>
                Salle <span className="font-medium text-foreground">{cible.salle.nom}</span>
                {cible.salle.departement && (
                  <>
                    {" "}
                    ({cible.salle.departement.code})
                  </>
                )}
              </>
            ) : (
              <>
                <span className="font-medium text-foreground">
                  {cible.departement.code} — {cible.departement.nom}
                </span>
                , {cible.salles} salle{cible.salles > 1 ? "s" : ""} — une page par salle, dans l&apos;ordre des
                niveaux
              </>
            )}
            , semaine{" "}
            <span className="font-medium text-foreground">
              S{semaine.numero} · {plageSemaine(semaine.date_debut, semaine.date_fin)}
            </span>
{" "}
            — en-tête bilingue au nom du département, une colonne par jour avec les présences relevées,
            séances de la semaine préremplies, étudiants FM signalés.
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
          <Button className="gap-1.5" disabled={enCours} onClick={telecharger}>
            <FileDown className="size-4" />
            {enCours ? "Génération…" : departement ? "Télécharger toutes les listes" : "Télécharger le PDF"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
