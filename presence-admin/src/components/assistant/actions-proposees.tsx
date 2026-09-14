"use client";

import { useMemo, useState } from "react";
import {
  ArrowLeftRight,
  BookOpen,
  CalendarPlus,
  CalendarX2,
  Check,
  ClipboardCopy,
  Download,
  GraduationCap,
  Loader2,
  Pencil,
  Trash2,
  UserPlus,
  Users,
  X,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  useAppliquerActions,
  useIgnorerActions,
  type ActionIA,
  type TypeAction,
} from "@/hooks/use-assistant";
import { telechargerFichier } from "@/lib/api-client";
import { cn } from "@/lib/utils";

const ICONES: Record<TypeAction, typeof BookOpen> = {
  creer_cours: CalendarPlus,
  inscrire_etudiant: UserPlus,
  creer_enseignant: GraduationCap,
  modifier_seance: Pencil,
  supprimer_seance: CalendarX2,
  supprimer_cours: Trash2,
  changer_salle_etudiant: ArrowLeftRight,
  importer_etudiants: Users,
  importer_cours: CalendarPlus,
};

const LIBELLES: Record<TypeAction, string> = {
  creer_cours: "Cours",
  inscrire_etudiant: "Inscription",
  creer_enseignant: "Enseignant",
  modifier_seance: "Séance",
  supprimer_seance: "Annulation",
  supprimer_cours: "Suppression",
  changer_salle_etudiant: "Salle",
  importer_etudiants: "Import",
  importer_cours: "Import",
};

interface ApercuLigne {
  nom?: string;
  matricule?: string;
  formation?: string | null;
  salle_nom?: string | null;
  matiere_nom?: string;
  jour?: string;
  heure_debut?: string;
  heure_fin?: string;
  anomalie?: string | null;
}

interface ParametresImport {
  total?: number;
  anomalies?: number;
  apercu?: ApercuLigne[];
  source?: { fichier?: string; feuille?: string; pages?: number };
}

interface Echec {
  ligne?: string | number;
  source?: string | number;
  nom?: string;
  matricule?: string;
  cours?: string;
  motif: string;
}

function estImport(a: ActionIA) {
  return a.type === "importer_etudiants" || a.type === "importer_cours";
}

const DESTRUCTIVES: TypeAction[] = ["supprimer_seance", "supprimer_cours"];

/**
 * Les propositions de l'assistant, cochées par défaut : l'admin décoche ce
 * qu'il refuse et applique le reste d'un clic. Les actions déjà traitées
 * restent visibles avec leur résultat — dont les mots de passe initiaux,
 * qu'on ne reverra plus ailleurs.
 */
export function ActionsProposees({ conversationId, actions }: { conversationId: number; actions: ActionIA[] }) {
  const enAttente = useMemo(() => actions.filter((a) => a.statut === "en_attente"), [actions]);
  const traitees = useMemo(() => actions.filter((a) => a.statut !== "en_attente").reverse(), [actions]);
  const [decochees, setDecochees] = useState<Set<string>>(new Set());
  const [historiqueOuvert, setHistoriqueOuvert] = useState(false);

  const appliquer = useAppliquerActions(conversationId);
  const ignorer = useIgnorerActions(conversationId);

  const cochees = enAttente.filter((a) => !decochees.has(a.id)).map((a) => a.id);
  const occupe = appliquer.isPending || ignorer.isPending;

  function basculer(id: string, coche: boolean) {
    setDecochees((prev) => {
      const suivant = new Set(prev);
      if (coche) suivant.delete(id);
      else suivant.add(id);
      return suivant;
    });
  }

  function lancer() {
    appliquer.mutate(cochees, {
      onSuccess: (r) => {
        if (r.echouees === 0) toast.success(`${r.appliquees} action${r.appliquees > 1 ? "s" : ""} appliquée${r.appliquees > 1 ? "s" : ""}.`);
        else toast.warning(`${r.appliquees} appliquée${r.appliquees > 1 ? "s" : ""}, ${r.echouees} en échec — voir le détail.`);
        setDecochees(new Set());
      },
      onError: () => toast.error("L'application a échoué."),
    });
  }

  if (actions.length === 0) return null;

  return (
    <div className="flex flex-col gap-3">
      {enAttente.length > 0 && (
        <div className="rounded-2xl border border-primary/30 bg-primary/5 p-3">
          <div className="mb-2 flex items-center justify-between gap-2 px-1">
            <p className="text-xs font-semibold uppercase tracking-wide text-primary">
              {enAttente.length} proposition{enAttente.length > 1 ? "s" : ""} à valider
            </p>
            <button
              type="button"
              className="text-[11.5px] text-muted-foreground underline-offset-2 hover:underline"
              onClick={() => setDecochees(cochees.length === 0 ? new Set() : new Set(enAttente.map((a) => a.id)))}
            >
              {cochees.length === 0 ? "Tout cocher" : "Tout décocher"}
            </button>
          </div>

          <ul className="flex flex-col gap-1">
            {enAttente.map((a) => {
              const Icone = ICONES[a.type] ?? BookOpen;
              const coche = !decochees.has(a.id);
              return (
                <li key={a.id}>
                  <label
                    className={cn(
                      "flex cursor-pointer items-start gap-2.5 rounded-xl px-2.5 py-2 transition-colors hover:bg-card",
                      !coche && "opacity-60",
                    )}
                  >
                    <Checkbox checked={coche} onCheckedChange={(v) => basculer(a.id, Boolean(v))} className="mt-0.5" />
                    <Icone
                      className={cn(
                        "mt-0.5 size-4 shrink-0",
                        DESTRUCTIVES.includes(a.type) ? "text-destructive" : "text-primary",
                      )}
                    />
                    <span className="min-w-0 flex-1 text-[13px] leading-snug text-foreground">
                      <span className="mr-1.5 rounded bg-secondary px-1 py-px text-[10px] font-semibold uppercase text-secondary-foreground">
                        {LIBELLES[a.type] ?? a.type}
                      </span>
                      {a.resume}
                      {estImport(a) && <ApercuImport action={a} />}
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>

          <div className="mt-3 flex items-center justify-end gap-2 px-1">
            <Button
              size="sm"
              variant="ghost"
              disabled={occupe || cochees.length === 0}
              onClick={() => ignorer.mutate(cochees, { onSuccess: () => setDecochees(new Set()) })}
            >
              Ignorer
            </Button>
            <Button size="sm" className="gap-1.5" disabled={occupe || cochees.length === 0} onClick={lancer}>
              {appliquer.isPending ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
              Appliquer {cochees.length > 0 ? `(${cochees.length})` : ""}
            </Button>
          </div>
        </div>
      )}

      {traitees.length > 0 && (
        <div className="rounded-2xl border border-border bg-card p-3">
          <button
            type="button"
            className="flex w-full items-center justify-between px-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
            onClick={() => setHistoriqueOuvert((o) => !o)}
            aria-expanded={historiqueOuvert}
          >
            <span>
              {traitees.filter((a) => a.statut === "appliquee").length} appliquée{traitees.filter((a) => a.statut === "appliquee").length > 1 ? "s" : ""}
              {traitees.some((a) => a.statut === "echouee") && ` · ${traitees.filter((a) => a.statut === "echouee").length} en échec`}
              {traitees.some((a) => a.statut === "ignoree") && ` · ${traitees.filter((a) => a.statut === "ignoree").length} ignorée${traitees.filter((a) => a.statut === "ignoree").length > 1 ? "s" : ""}`}
            </span>
            <span className="text-[11px] normal-case tracking-normal">{historiqueOuvert ? "Masquer" : "Détail"}</span>
          </button>

          {historiqueOuvert && (
            <ul className="mt-2 flex flex-col divide-y divide-border">
              {traitees.map((a) => (
                <li key={a.id} className="flex items-start gap-2.5 py-2 text-[13px]">
                  {a.statut === "appliquee" ? (
                    <Check className="mt-0.5 size-4 shrink-0 text-success" />
                  ) : a.statut === "echouee" ? (
                    <X className="mt-0.5 size-4 shrink-0 text-destructive" />
                  ) : (
                    <X className="mt-0.5 size-4 shrink-0 text-muted-foreground/60" />
                  )}
                  <div className="min-w-0 flex-1">
                    <p className={cn("leading-snug", a.statut === "ignoree" && "text-muted-foreground line-through")}>{a.resume}</p>
                    {a.resultat?.message && (
                      <p className={cn("mt-0.5 text-[12px]", a.statut === "echouee" ? "text-destructive" : "text-muted-foreground")}>
                        {a.resultat.message}
                      </p>
                    )}
                    <Identifiants details={a.resultat?.details} />
                    {estImport(a) && a.statut !== "ignoree" && (
                      <ResultatImport conversationId={conversationId} action={a} />
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {traitees.some((a) => a.resultat?.details?.mot_de_passe_initial) && (
        <CopierIdentifiants actions={traitees} />
      )}
    </div>
  );
}

/** Ce que contient un import avant application : effectif, source, premières lignes, anomalies. */
function ApercuImport({ action }: { action: ActionIA }) {
  const p = action.parametres as ParametresImport;
  const [ouvert, setOuvert] = useState(false);
  const total = p.total ?? 0;
  const anomalies = p.anomalies ?? 0;

  return (
    <span className="mt-1 block text-[12px] font-normal text-muted-foreground">
      <span>
        {total} ligne{total > 1 ? "s" : ""}
        {p.source?.feuille ? ` · feuille « ${p.source.feuille} »` : ""}
        {p.source?.pages ? ` · ${p.source.pages} pages lues` : ""}
        {anomalies > 0 && (
          <span className="ml-1.5 rounded bg-warning/20 px-1 py-px font-semibold text-warning-foreground">
            {anomalies} anomalie{anomalies > 1 ? "s" : ""} (ignorée{anomalies > 1 ? "s" : ""} à l&apos;application)
          </span>
        )}
      </span>
      {(p.apercu?.length ?? 0) > 0 && (
        <>
          {" · "}
          <button
            type="button"
            className="text-primary underline-offset-2 hover:underline"
            onClick={(e) => {
              e.preventDefault();
              setOuvert((o) => !o);
            }}
          >
            {ouvert ? "masquer l'aperçu" : "voir l'aperçu"}
          </button>
          {ouvert && (
            <span className="mt-1.5 block overflow-x-auto rounded-lg border border-border bg-card">
              <table className="w-full text-[11.5px]">
                <tbody>
                  {p.apercu!.map((l, i) => (
                    <tr key={i} className={cn("border-b border-border last:border-b-0", l.anomalie && "text-warning-foreground")}>
                      {action.type === "importer_etudiants" ? (
                        <>
                          <td className="px-2 py-1 font-medium text-foreground">{l.nom}</td>
                          <td className="px-2 py-1 tabular-nums">{l.matricule}</td>
                          <td className="px-2 py-1">{l.formation ?? "—"}</td>
                          <td className="px-2 py-1">{l.salle_nom ?? "—"}</td>
                        </>
                      ) : (
                        <>
                          <td className="px-2 py-1 font-medium text-foreground">{l.matiere_nom}</td>
                          <td className="px-2 py-1">{l.jour?.toLowerCase()} {l.heure_debut}–{l.heure_fin}</td>
                          <td className="px-2 py-1">{l.salle_nom ?? "—"}</td>
                        </>
                      )}
                      <td className="px-2 py-1 text-warning-foreground">{l.anomalie ?? ""}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {total > p.apercu!.length && (
                <span className="block px-2 py-1 text-[11px] text-muted-foreground">
                  … et {total - p.apercu!.length} autres lignes
                </span>
              )}
            </span>
          )}
        </>
      )}
    </span>
  );
}

/** Après application d'un import : comptes, échecs détaillés, export des identifiants. */
function ResultatImport({ conversationId, action }: { conversationId: number; action: ActionIA }) {
  const d = (action.resultat?.details ?? {}) as {
    crees?: number;
    total?: number;
    echecs?: Echec[];
    echecs_total?: number;
    identifiants_count?: number;
    seances_creees?: number;
  };
  const [echecsOuverts, setEchecsOuverts] = useState(false);
  const [telechargement, setTelechargement] = useState(false);

  return (
    <div className="mt-1.5 flex flex-col gap-1.5">
      {(d.identifiants_count ?? 0) > 0 && (
        <Button
          size="sm"
          variant="outline"
          className="w-fit gap-1.5"
          disabled={telechargement}
          onClick={() => {
            setTelechargement(true);
            telechargerFichier(
              `/api/assistant/conversations/${conversationId}/actions/${action.id}/identifiants.csv`,
              "identifiants.csv",
            )
              .then(() => toast.success("Identifiants téléchargés — à remettre aux étudiants."))
              .catch(() => toast.error("Téléchargement impossible."))
              .finally(() => setTelechargement(false));
          }}
        >
          <Download className="size-3.5" />
          Identifiants des {d.identifiants_count} comptes créés (CSV)
        </Button>
      )}
      {(d.echecs_total ?? 0) > 0 && (
        <div>
          <button
            type="button"
            className="text-[12px] text-destructive underline-offset-2 hover:underline"
            onClick={() => setEchecsOuverts((o) => !o)}
          >
            {d.echecs_total} ligne{(d.echecs_total ?? 0) > 1 ? "s" : ""} en échec — {echecsOuverts ? "masquer" : "voir"}
          </button>
          {echecsOuverts && (
            <ul className="mt-1 max-h-48 overflow-y-auto rounded-lg border border-destructive/30 bg-destructive/5 px-2.5 py-1.5 text-[11.5px]">
              {(d.echecs ?? []).map((e, i) => (
                <li key={i} className="py-0.5">
                  <span className="font-medium text-foreground">{e.nom ?? e.cours ?? `ligne ${e.ligne ?? e.source}`}</span>
                  {e.matricule ? ` (${e.matricule})` : ""} — <span className="text-destructive">{e.motif}</span>
                </li>
              ))}
              {(d.echecs_total ?? 0) > (d.echecs?.length ?? 0) && (
                <li className="py-0.5 text-muted-foreground">… {(d.echecs_total ?? 0) - (d.echecs?.length ?? 0)} autres</li>
              )}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}

/** Matricule et mot de passe initial d'un compte créé : à remettre à la personne. */
function Identifiants({ details }: { details?: Record<string, unknown> }) {
  const mdp = details?.mot_de_passe_initial;
  if (typeof mdp !== "string") return null;
  const identifiant = (details?.matricule ?? details?.telephone) as string | undefined;

  return (
    <p className="mt-1 inline-flex items-center gap-2 rounded-lg bg-muted px-2 py-1 font-mono text-[12px] text-foreground">
      {identifiant && <span>{identifiant}</span>}
      <span className="text-muted-foreground">·</span>
      <span>mot de passe {mdp}</span>
    </p>
  );
}

function CopierIdentifiants({ actions }: { actions: ActionIA[] }) {
  const lignes = actions
    .filter((a) => a.statut === "appliquee" && typeof a.resultat?.details?.mot_de_passe_initial === "string")
    .map((a) => {
      const d = a.resultat!.details;
      return `${a.resume} — identifiant ${d.matricule ?? d.telephone} — mot de passe ${d.mot_de_passe_initial}`;
    });

  if (lignes.length === 0) return null;

  return (
    <Button
      size="sm"
      variant="outline"
      className="w-fit gap-1.5"
      onClick={() =>
        navigator.clipboard
          .writeText(lignes.join("\n"))
          .then(() => toast.success(`${lignes.length} identifiant${lignes.length > 1 ? "s" : ""} copié${lignes.length > 1 ? "s" : ""}.`))
          .catch(() => toast.error("Copie impossible dans ce navigateur."))
      }
    >
      <ClipboardCopy className="size-3.5" />
      Copier les identifiants créés ({lignes.length})
    </Button>
  );
}
