"use client";

import { useMemo, useState } from "react";
import {
  ArrowLeftRight,
  BookOpen,
  CalendarPlus,
  CalendarX2,
  Check,
  ClipboardCopy,
  GraduationCap,
  Loader2,
  Pencil,
  Trash2,
  UserPlus,
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
import { cn } from "@/lib/utils";

const ICONES: Record<TypeAction, typeof BookOpen> = {
  creer_cours: CalendarPlus,
  inscrire_etudiant: UserPlus,
  creer_enseignant: GraduationCap,
  modifier_seance: Pencil,
  supprimer_seance: CalendarX2,
  supprimer_cours: Trash2,
  changer_salle_etudiant: ArrowLeftRight,
};

const LIBELLES: Record<TypeAction, string> = {
  creer_cours: "Cours",
  inscrire_etudiant: "Inscription",
  creer_enseignant: "Enseignant",
  modifier_seance: "Séance",
  supprimer_seance: "Annulation",
  supprimer_cours: "Suppression",
  changer_salle_etudiant: "Salle",
};

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
