"use client";

import { useMemo } from "react";
import { AlertTriangle, BadgeCheck, Crown, Loader2, Lock, Radio, ShieldBan, ShieldOff } from "lucide-react";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  symbole,
  type EtudiantFeuille,
  type FeuillePresence,
  type SeanceFeuille,
  type Symboles,
} from "@/hooks/use-feuille-presence";
import { formaterYmd, type Ymd } from "@/lib/dates";
import { cn } from "@/lib/utils";
import type { PresenceState } from "@/types/api";
import { JOURS } from "@/components/emploi-du-temps/constantes";

interface Props {
  feuille: FeuillePresence;
  symboles: Symboles;
  filtre: string;
  aujourdhui: Ymd | null;
  enCours: { etudiantId: number; seanceId: number } | null;
  onEtudiant: (etudiant: EtudiantFeuille) => void;
  onBasculer: (etudiant: EtudiantFeuille, seance: SeanceFeuille, etat: PresenceState) => void;
}

export function initiales(nom: string) {
  return nom
    .split(" ")
    .filter(Boolean)
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

function normaliser(texte: string) {
  return texte.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

/**
 * Taux de la semaine : présences sur les séances où l'étudiant a été noté
 * (présent ou absent). Une séance tenue sans appel validé pour lui ne le
 * pénalise pas — on ne sait pas.
 */
export function tauxDeLaSemaine(etudiant: EtudiantFeuille, seances: SeanceFeuille[]) {
  const notees = seances.filter((s) => s.statut === "tenue" && etudiant.presences[s.id] != null);
  const presents = notees.filter((s) => etudiant.presences[s.id] === "present").length;
  return { presents, total: notees.length };
}

/**
 * La feuille de présence à l'écran : un étudiant par ligne, une séance par
 * colonne, et dans chaque case le même symbole que sur le PDF. Cliquer une
 * case bascule la présence — le pouvoir de l'admin, sans passer par un menu.
 */
export function GrillePresences({
  feuille,
  symboles,
  filtre,
  aujourdhui,
  enCours,
  onEtudiant,
  onBasculer,
}: Props) {
  const { seances, delegue } = feuille;

  const etudiants = useMemo(() => {
    const cle = normaliser(filtre.trim());
    if (!cle) return feuille.etudiants;
    return feuille.etudiants.filter(
      (e) => normaliser(e.name).includes(cle) || e.phone.toLowerCase().includes(cle),
    );
  }, [feuille.etudiants, filtre]);

  // Colonnes groupées par jour, dans l'ordre de la semaine.
  const jours = useMemo(() => {
    const groupes = new Map<string, SeanceFeuille[]>();
    for (const s of seances) {
      const liste = groupes.get(s.date_seance) ?? [];
      liste.push(s);
      groupes.set(s.date_seance, liste);
    }
    return [...groupes.entries()].sort(([a], [b]) => a.localeCompare(b));
  }, [seances]);

  const presentsParSeance = useMemo(() => {
    const compte = new Map<number, number>();
    for (const s of seances) {
      compte.set(s.id, etudiants.filter((e) => e.presences[s.id] === "present").length);
    }
    return compte;
  }, [seances, etudiants]);

  return (
    <div className="overflow-x-auto rounded-2xl border border-border bg-card shadow-xs">
      <table className="w-full border-separate border-spacing-0 text-[13px]">
        <thead>
          <tr>
            <th
              rowSpan={2}
              className="sticky left-0 z-20 min-w-[260px] border-b border-r border-border bg-card px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground"
            >
              {etudiants.length} étudiant{etudiants.length > 1 ? "s" : ""}
              {filtre.trim() && feuille.etudiants.length !== etudiants.length && (
                <span className="font-normal"> sur {feuille.etudiants.length}</span>
              )}
            </th>
            {jours.map(([date, liste]) => {
              const jour = JOURS.find((j) => j.valeur === liste[0].jour);
              const estAujourdhui = date === aujourdhui;
              return (
                <th
                  key={date}
                  colSpan={liste.length}
                  className={cn(
                    "border-b border-l border-border px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide",
                    estAujourdhui ? "bg-primary/8 text-primary" : "text-muted-foreground",
                  )}
                >
                  {jour?.court}{" "}
                  <span className="font-display text-sm normal-case tracking-normal text-foreground">
                    {formaterYmd(date, { day: "numeric" })}
                  </span>
                  {estAujourdhui && <span className="ml-1.5 normal-case tracking-normal">· aujourd&apos;hui</span>}
                </th>
              );
            })}
            <th
              rowSpan={2}
              className="min-w-[120px] border-b border-l border-border px-3 py-2 text-center text-xs font-semibold text-muted-foreground"
            >
              Semaine
            </th>
          </tr>
          <tr>
            {jours.flatMap(([date, liste]) =>
              liste.map((s, i) => (
                <th
                  key={s.id}
                  title={`${s.matiere ?? "Séance"} · ${s.enseignant ?? ""} · ${s.heure_debut}–${s.heure_fin}`}
                  className={cn(
                    "min-w-[92px] border-b border-border px-1.5 py-1.5 text-center align-top font-normal",
                    i === 0 && "border-l",
                    date === aujourdhui && "bg-primary/8",
                  )}
                >
                  <div className="flex items-center justify-center gap-1 text-[12px] font-semibold tabular-nums text-foreground">
                    {s.heure_debut}
                    <IconeStatut seance={s} />
                  </div>
                  <div className="mt-0.5 max-w-[104px] truncate text-[11px] leading-tight text-muted-foreground">
                    {s.matiere ?? "Séance"}
                  </div>
                </th>
              )),
            )}
          </tr>
        </thead>

        <tbody>
          {etudiants.map((e) => {
            const taux = tauxDeLaSemaine(e, seances);
            const estDelegue = e.role === "Delegue" || delegue?.id === e.id;
            return (
              <tr key={e.id} className="group">
                <td className="sticky left-0 z-10 border-b border-r border-border bg-card px-3 py-1.5 group-hover:bg-muted/40">
                  <button
                    type="button"
                    onClick={() => onEtudiant(e)}
                    className="flex w-full items-center gap-2.5 rounded-lg px-1 py-0.5 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                  >
                    <Avatar className="size-8">
                      <AvatarFallback
                        className={cn(
                          "text-[11px] font-semibold",
                          e.formation === "FM" ? "bg-warning/25 text-warning-foreground" : "bg-accent text-accent-foreground",
                        )}
                      >
                        {initiales(e.name)}
                      </AvatarFallback>
                    </Avatar>
                    <span className="min-w-0 flex-1">
                      <span className="flex items-center gap-1.5">
                        <span className="truncate font-medium text-foreground">{e.name}</span>
                        {estDelegue && <Crown className="size-3.5 shrink-0 text-warning-foreground" aria-label="Délégué" />}
                        {e.presence_automatique && (
                          <BadgeCheck className="size-3.5 shrink-0 text-primary" aria-label="Toujours présent" />
                        )}
                        {e.statut_compte === "restreint" && (
                          <ShieldOff className="size-3.5 shrink-0 text-warning-foreground" aria-label="Compte restreint" />
                        )}
                        {e.statut_compte === "bloque" && (
                          <ShieldBan className="size-3.5 shrink-0 text-destructive" aria-label="Compte bloqué" />
                        )}
                      </span>
                      <span className="flex items-center gap-1.5 text-[11.5px] text-muted-foreground">
                        <span className="tabular-nums">{e.phone}</span>
                        {e.formation && (
                          <span
                            className={cn(
                              "rounded px-1 py-px text-[10px] font-semibold",
                              e.formation === "FM"
                                ? "bg-warning/25 text-warning-foreground"
                                : "bg-secondary text-secondary-foreground",
                            )}
                          >
                            {e.formation}
                          </span>
                        )}
                      </span>
                    </span>
                  </button>
                </td>

                {jours.flatMap(([date, liste]) =>
                  liste.map((s, i) => (
                    <td
                      key={s.id}
                      className={cn(
                        "border-b border-border p-0 text-center group-hover:bg-muted/40",
                        i === 0 && "border-l",
                        date === aujourdhui && "bg-primary/5",
                      )}
                    >
                      <Case
                        etudiant={e}
                        seance={s}
                        etat={e.presences[s.id] ?? null}
                        symboles={symboles}
                        occupe={enCours?.etudiantId === e.id && enCours?.seanceId === s.id}
                        onBasculer={(etat) => onBasculer(e, s, etat)}
                      />
                    </td>
                  )),
                )}

                <td className="border-b border-l border-border px-3 py-1.5 group-hover:bg-muted/40">
                  <Taux presents={taux.presents} total={taux.total} />
                </td>
              </tr>
            );
          })}

          {etudiants.length === 0 && (
            <tr>
              <td colSpan={seances.length + 2} className="px-4 py-10 text-center text-sm text-muted-foreground">
                {filtre.trim()
                  ? "Aucun étudiant de cette salle ne correspond à la recherche."
                  : "Aucun étudiant rattaché à cette salle pour l'instant."}
              </td>
            </tr>
          )}
        </tbody>

        {etudiants.length > 0 && seances.length > 0 && (
          <tfoot>
            <tr>
              <td className="sticky left-0 z-10 border-r border-border bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground">
                Présents / effectif
              </td>
              {jours.flatMap(([, liste]) =>
                liste.map((s, i) => (
                  <td
                    key={s.id}
                    className={cn(
                      "bg-muted/50 px-1 py-2 text-center text-xs tabular-nums text-muted-foreground",
                      i === 0 && "border-l border-border",
                    )}
                  >
                    {s.statut === "a_venir" ? "—" : `${presentsParSeance.get(s.id) ?? 0}/${etudiants.length}`}
                  </td>
                )),
              )}
              <td className="border-l border-border bg-muted/50" />
            </tr>
          </tfoot>
        )}
      </table>
    </div>
  );
}

function IconeStatut({ seance }: { seance: SeanceFeuille }) {
  switch (seance.statut) {
    case "tenue":
      return seance.presences_locked ? (
        <Lock className="size-3 text-muted-foreground" aria-label="Appel validé par le délégué" />
      ) : null;
    case "en_cours":
      return <Radio className="size-3 animate-pulse text-success" aria-label="Séance en cours" />;
    case "non_validee":
      return <AlertTriangle className="size-3 text-warning-foreground" aria-label="Appel jamais validé" />;
    default:
      return null;
  }
}

function Case({
  etudiant,
  seance,
  etat,
  symboles,
  occupe,
  onBasculer,
}: {
  etudiant: EtudiantFeuille;
  seance: SeanceFeuille;
  etat: PresenceState | null;
  symboles: Symboles;
  occupe: boolean;
  onBasculer: (etat: PresenceState) => void;
}) {
  const aVenir = seance.statut === "a_venir";
  const prochain: PresenceState = etat === "present" ? "absent" : "present";
  const libelleEtat =
    etat === "present" ? "présent" : etat === "absent" ? "absent" : aVenir ? "séance à venir" : "non renseigné";

  return (
    <button
      type="button"
      disabled={aVenir || occupe}
      onClick={() => onBasculer(prochain)}
      aria-label={`${etudiant.name}, ${seance.heure_debut} ${seance.matiere ?? ""} : ${libelleEtat}${aVenir ? "" : `. Cliquer pour marquer ${prochain === "present" ? "présent" : "absent"}`}`}
      title={aVenir ? "Séance à venir" : `Marquer ${prochain === "present" ? "présent" : "absent"}`}
      className={cn(
        "flex h-11 w-full items-center justify-center font-display text-[15px] font-bold tabular-nums transition-colors outline-none",
        "focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset",
        etat === "present" && "text-success hover:bg-success/10",
        etat === "absent" && "text-destructive hover:bg-destructive/10",
        etat === null && !aVenir && "text-muted-foreground/50 hover:bg-primary/10 hover:text-primary",
        aVenir && "cursor-default text-muted-foreground/25",
      )}
    >
      {occupe ? (
        <Loader2 className="size-4 animate-spin text-muted-foreground" />
      ) : etat ? (
        symbole(symboles, etat)
      ) : seance.statut === "non_validee" ? (
        "–"
      ) : (
        "·"
      )}
    </button>
  );
}

function Taux({ presents, total }: { presents: number; total: number }) {
  if (total === 0) {
    return <span className="block text-center text-xs text-muted-foreground/60">—</span>;
  }
  const ratio = presents / total;
  return (
    <div className="flex items-center gap-2">
      <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
        <div
          className={cn(
            "h-full rounded-full",
            ratio >= 0.75 ? "bg-success" : ratio >= 0.5 ? "bg-warning" : "bg-destructive",
          )}
          style={{ width: `${Math.round(ratio * 100)}%` }}
        />
      </div>
      <span className="w-9 text-right text-xs font-semibold tabular-nums text-foreground">
        {presents}/{total}
      </span>
    </div>
  );
}
