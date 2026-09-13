"use client";

import {
  ArrowLeftRight,
  BookOpen,
  Crown,
  DoorOpen,
  GraduationCap,
  Phone,
  ShieldBan,
  ShieldOff,
  Trash2,
  UserCheck,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Sheet, SheetContent } from "@/components/ui/sheet";
import {
  symbole,
  type EtudiantFeuille,
  type FeuillePresence,
  type Symboles,
} from "@/hooks/use-feuille-presence";
import { formaterYmd } from "@/lib/dates";
import { cn } from "@/lib/utils";
import type { StatutCompte } from "@/types/api";
import type { ActionEtudiant } from "./dialogues";
import { initiales, tauxDeLaSemaine } from "./grille-presences";

export const STATUTS: Record<StatutCompte, { label: string; classe: string; explication: string }> = {
  actif: {
    label: "Actif",
    classe: "bg-success/15 text-success",
    explication: "Connexion et pointage normaux.",
  },
  restreint: {
    label: "Restreint",
    classe: "bg-warning/20 text-warning-foreground",
    explication: "Peut se connecter et consulter, mais le pointage lui est refusé.",
  },
  bloque: {
    label: "Bloqué",
    classe: "bg-destructive/10 text-destructive",
    explication: "Ne peut plus se connecter ; sa session a été coupée.",
  },
};

interface Props {
  etudiant: EtudiantFeuille;
  feuille: FeuillePresence;
  symboles: Symboles;
  onAction: (type: Exclude<ActionEtudiant["type"], "presence">) => void;
  onFermer: () => void;
}

/**
 * Fiche d'un étudiant, ouverte depuis la grille : son compte, sa semaine,
 * et les actions de l'admin sur son compte. Les présences se corrigent dans
 * la grille elle-même, pas ici.
 */
export function PanneauEtudiant({ etudiant: e, feuille, symboles, onAction, onFermer }: Props) {
  const statut = STATUTS[e.statut_compte];
  const taux = tauxDeLaSemaine(e, feuille.seances);
  const estDelegue = e.role === "Delegue";

  return (
    <Sheet open onOpenChange={(o) => !o && onFermer()}>
      <SheetContent side="right" className="w-full max-w-md bg-card text-card-foreground sm:w-[420px]">
        <div className="flex h-full flex-col overflow-y-auto">
          <div className="flex items-start gap-3 border-b border-border px-5 pt-5 pb-4">
            <Avatar className="size-12">
              <AvatarFallback
                className={cn(
                  "text-sm font-semibold",
                  e.formation === "FM" ? "bg-warning/25 text-warning-foreground" : "bg-accent text-accent-foreground",
                )}
              >
                {initiales(e.name)}
              </AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1 pr-8">
              <h2 className="font-display truncate text-lg font-semibold text-foreground">{e.name}</h2>
              <div className="mt-1 flex flex-wrap items-center gap-1.5">
                <Badge variant="outline" className="gap-1 tabular-nums">
                  <Phone className="size-3" /> {e.phone}
                </Badge>
                {e.formation && (
                  <Badge
                    className={cn(
                      "border-transparent",
                      e.formation === "FM"
                        ? "bg-warning/25 text-warning-foreground"
                        : "bg-secondary text-secondary-foreground",
                    )}
                  >
                    {e.formation === "FM" ? "FM · formation migrante" : e.formation}
                  </Badge>
                )}
                {estDelegue && (
                  <Badge className="gap-1 border-transparent bg-warning/20 text-warning-foreground">
                    <Crown className="size-3" /> Délégué
                  </Badge>
                )}
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-5 px-5 py-4">
            <section className="flex flex-col gap-2">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Compte</h3>
              <div className="rounded-xl border border-border p-3.5">
                <div className="flex items-center justify-between gap-3">
                  <span className={cn("rounded-full px-2.5 py-1 text-xs font-semibold", statut.classe)}>
                    {statut.label}
                  </span>
                  <span className="text-right text-xs text-muted-foreground">{statut.explication}</span>
                </div>
                {e.motif_statut && e.statut_compte !== "actif" && (
                  <p className="mt-2.5 rounded-lg bg-muted/60 px-3 py-2 text-[13px] text-foreground">
                    Motif : {e.motif_statut}
                  </p>
                )}
              </div>
            </section>

            <section className="flex flex-col gap-2">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Rattachement</h3>
              <div className="grid grid-cols-1 gap-2 rounded-xl border border-border p-3.5 text-[13px]">
                <Ligne icone={DoorOpen} label="Salle" valeur={`${feuille.salle.nom} · ${feuille.salle.formation}`} />
                {feuille.salle.filiere && <Ligne icone={BookOpen} label="Filière" valeur={feuille.salle.filiere} />}
                {feuille.salle.niveau && <Ligne icone={GraduationCap} label="Niveau" valeur={feuille.salle.niveau} />}
              </div>
            </section>

            <section className="flex flex-col gap-2">
              <div className="flex items-baseline justify-between">
                <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Cette semaine
                </h3>
                {taux.total > 0 && (
                  <span className="text-xs font-semibold tabular-nums text-foreground">
                    {taux.presents}/{taux.total} séance{taux.total > 1 ? "s" : ""} tenue{taux.total > 1 ? "s" : ""}
                  </span>
                )}
              </div>
              {feuille.seances.length === 0 ? (
                <p className="rounded-xl border border-dashed border-border px-3.5 py-3 text-[13px] text-muted-foreground">
                  Aucune séance programmée cette semaine dans sa salle.
                </p>
              ) : (
                <ul className="divide-y divide-border rounded-xl border border-border">
                  {feuille.seances.map((s) => {
                    const etat = e.presences[s.id] ?? null;
                    return (
                      <li key={s.id} className="flex items-center gap-3 px-3.5 py-2 text-[13px]">
                        <span className="w-16 shrink-0 tabular-nums text-muted-foreground">
                          {formaterYmd(s.date_seance, { weekday: "short", day: "numeric" })}
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate font-medium text-foreground">{s.matiere ?? "Séance"}</span>
                          <span className="block text-[11.5px] text-muted-foreground">
                            {s.heure_debut}–{s.heure_fin} · {s.enseignant ?? "—"}
                          </span>
                        </span>
                        <span
                          className={cn(
                            "font-display w-8 text-right text-[15px] font-bold tabular-nums",
                            etat === "present" && "text-success",
                            etat === "absent" && "text-destructive",
                            etat === null && "text-muted-foreground/40",
                          )}
                        >
                          {etat ? symbole(symboles, etat) : s.statut === "a_venir" ? "" : "·"}
                        </span>
                      </li>
                    );
                  })}
                </ul>
              )}
            </section>

            <section className="flex flex-col gap-2">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Actions</h3>
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <Button variant="outline" className="justify-start gap-2" onClick={() => onAction("salle")}>
                  <ArrowLeftRight className="size-4" /> Changer de salle
                </Button>
                {e.statut_compte !== "actif" && (
                  <Button variant="outline" className="justify-start gap-2 text-success" onClick={() => onAction("retablir")}>
                    <UserCheck className="size-4" /> Rétablir le compte
                  </Button>
                )}
                {e.statut_compte !== "restreint" && (
                  <Button
                    variant="outline"
                    className="justify-start gap-2 text-warning-foreground"
                    onClick={() => onAction("restreindre")}
                  >
                    <ShieldOff className="size-4" /> Restreindre
                  </Button>
                )}
                {e.statut_compte !== "bloque" && (
                  <Button variant="outline" className="justify-start gap-2 text-destructive" onClick={() => onAction("bloquer")}>
                    <ShieldBan className="size-4" /> Bloquer
                  </Button>
                )}
              </div>
              <Button
                variant="ghost"
                className="mt-1 justify-start gap-2 text-muted-foreground hover:text-destructive"
                onClick={() => onAction("supprimer")}
              >
                <Trash2 className="size-4" /> Supprimer définitivement le compte
              </Button>
            </section>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

function Ligne({ icone: Icone, label, valeur }: { icone: typeof Phone; label: string; valeur: string }) {
  return (
    <div className="flex items-center gap-2.5">
      <Icone className="size-4 shrink-0 text-muted-foreground" />
      <span className="w-16 shrink-0 text-muted-foreground">{label}</span>
      <span className="truncate font-medium text-foreground">{valeur}</span>
    </div>
  );
}
