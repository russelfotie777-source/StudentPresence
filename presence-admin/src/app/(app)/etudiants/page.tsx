"use client";

import { useMemo, useState } from "react";
import { toast } from "sonner";
import { CalendarX2, Crown, DoorOpen, FileDown, ShieldBan, ShieldOff, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { salleHooks, semaineHooks, type Salle } from "@/hooks/use-catalog";
import {
  useChangerSalle,
  useChangerStatut,
  useForcerPresence,
  useSupprimerEtudiant,
} from "@/hooks/use-etudiants";
import {
  useFeuillePresence,
  useSymboles,
  type EtudiantFeuille,
  type FeuillePresence,
  type SeanceFeuille,
} from "@/hooks/use-feuille-presence";
import { useHeureDouala } from "@/hooks/use-heure";
import { libelleSalle } from "@/lib/catalogue";
import { cn } from "@/lib/utils";
import type { PresenceState, User } from "@/types/api";
import { HorlogeDouala } from "@/components/horloge-douala";
import { NavigateurSemaine } from "@/components/navigateur-semaine";
import { GrillePresences, tauxDeLaSemaine } from "@/components/etudiants/grille-presences";
import { PanneauEtudiant } from "@/components/etudiants/panneau-etudiant";
import { RechercheGlobale } from "@/components/etudiants/recherche-globale";
import { DialogueListe } from "@/components/etudiants/dialogue-liste";
import { SelecteurSymboles } from "@/components/etudiants/selecteur-symboles";
import {
  DialogueConfirmation,
  DialogueSalle,
  DialogueSanction,
  type ActionEtudiant,
} from "@/components/etudiants/dialogues";

export default function EtudiantsPage() {
  const { data: salles } = salleHooks.useList();
  const { data: semaines } = semaineHooks.useList();
  const heure = useHeureDouala();
  const reglage = useSymboles();

  const [salleChoisie, setSalleChoisie] = useState<number | null>(null);
  const [semaineChoisie, setSemaineChoisie] = useState<number | null>(null);
  const [filtre, setFiltre] = useState("");
  const [etudiantOuvertId, setEtudiantOuvertId] = useState<number | null>(null);
  const [action, setAction] = useState<ActionEtudiant | null>(null);
  const [dialogueListe, setDialogueListe] = useState(false);
  const [enCours, setEnCours] = useState<{ etudiantId: number; seanceId: number } | null>(null);

  // Par défaut la première salle : l'onglet montre tout de suite une classe.
  const salleId = salleChoisie ?? salles?.[0]?.id ?? null;
  const requete = useFeuillePresence(salleId, semaineChoisie);
  const feuille = requete.data;
  const semaine = feuille?.semaine ?? null;
  const symboles = reglage.data?.symboles ?? feuille?.symboles ?? "coche";

  const semainesTriees = useMemo(
    () => [...(semaines ?? [])].sort((a, b) => a.numero - b.numero),
    [semaines],
  );

  // Un étudiant choisi dans la recherche globale s'ouvre dès que la feuille
  // de sa salle est chargée ; s'il n'y figure plus (changé de salle,
  // supprimé), le panneau disparaît simplement.
  const etudiantOuvert = feuille?.etudiants.find((e) => e.id === etudiantOuvertId) ?? null;

  const forcer = useForcerPresence();

  function basculer(e: EtudiantFeuille, s: SeanceFeuille, etat: PresenceState) {
    const precedent = e.presences[s.id] ?? null;
    setEnCours({ etudiantId: e.id, seanceId: s.id });
    forcer.mutate(
      { seanceId: s.id, etudiantId: e.id, etat },
      {
        onSuccess: () =>
          toast.success(
            `${e.name} : ${etat === "present" ? "présent" : "absent"} · ${s.heure_debut} ${s.matiere ?? ""}`,
            precedent
              ? {
                  action: {
                    label: "Annuler",
                    onClick: () => forcer.mutate({ seanceId: s.id, etudiantId: e.id, etat: precedent }),
                  },
                }
              : undefined,
          ),
        onSettled: () => setEnCours(null),
      },
    );
  }

  function ouvrirDepuisRecherche(u: User) {
    if (u.salle) setSalleChoisie(u.salle.id);
    setFiltre("");
    setEtudiantOuvertId(u.id);
  }

  const aucuneSemaine = semaines !== undefined && semainesTriees.length === 0;

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">Étudiants</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            La feuille de présence de chaque salle, semaine par semaine. Cliquez une case pour
            corriger une présence, un nom pour gérer le compte — et imprimez la liste officielle
            telle qu&apos;elle s&apos;affiche.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <HorlogeDouala heure={heure} />
          <SelecteurSymboles valeur={symboles} compact />
          <Button
            className="gap-1.5"
            disabled={!feuille || !semaine}
            onClick={() => setDialogueListe(true)}
          >
            <FileDown className="size-4" />
            Liste de présence
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <Select
            value={salleId ? String(salleId) : ""}
            onValueChange={(v) => {
              setSalleChoisie(v ? Number(v) : null);
              setFiltre("");
              setEtudiantOuvertId(null);
            }}
          >
            <SelectTrigger className="h-9 w-full rounded-lg sm:w-80">
              <SelectValue placeholder="Choisir une salle…">
                {() => {
                  const s = salles?.find((s) => s.id === salleId);
                  return s && `${libelleSalle(s)} · ${s.formation}`;
                }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {salles?.length === 0 && (
                <p className="px-2.5 py-2 text-xs text-muted-foreground">
                  Aucune salle : créez-en une dans le catalogue.
                </p>
              )}
              {salles?.map((s: Salle) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {libelleSalle(s)} · {s.formation}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <RechercheGlobale
            valeur={filtre}
            onChange={setFiltre}
            onChoisir={ouvrirDepuisRecherche}
            salleAffichee={salleId}
          />
        </div>

        {!aucuneSemaine && (
          <NavigateurSemaine
            semaines={semainesTriees}
            semaine={semaine}
            aujourdhui={heure.date}
            onChoisir={setSemaineChoisie}
          />
        )}
      </div>

      {feuille && <ResumeSalle feuille={feuille} />}

      {requete.isLoading || !feuille ? (
        <Skeleton className="h-[480px] w-full rounded-2xl" />
      ) : !semaine ? (
        <div className="flex items-center gap-2.5 rounded-xl border border-dashed border-border px-4 py-3 text-[13px] text-muted-foreground">
          <CalendarX2 className="size-4 shrink-0" />
          Aucune semaine n&apos;est définie : créez le calendrier du semestre depuis l&apos;onglet
          Emplois du temps pour voir les présences.
        </div>
      ) : (
        <>
          {feuille.seances.length === 0 && feuille.etudiants.length > 0 && (
            <div className="flex items-center gap-2.5 rounded-xl border border-dashed border-border px-4 py-3 text-[13px] text-muted-foreground">
              <CalendarX2 className="size-4 shrink-0" />
              Aucune séance programmée cette semaine dans cette salle : la grille n&apos;a pas de
              colonne. Programmez les cours depuis l&apos;onglet Emplois du temps.
            </div>
          )}
          <GrillePresences
            feuille={feuille}
            symboles={symboles}
            filtre={filtre}
            aujourdhui={heure.date}
            enCours={enCours}
            onEtudiant={(e) => setEtudiantOuvertId(e.id)}
            onBasculer={basculer}
          />
          <Legende symboles={symboles} />
        </>
      )}

      {etudiantOuvert && feuille && (
        <PanneauEtudiant
          etudiant={etudiantOuvert}
          feuille={feuille}
          symboles={symboles}
          onAction={(type) => setAction({ type, etudiant: versUser(etudiantOuvert, feuille) })}
          onFermer={() => setEtudiantOuvertId(null)}
        />
      )}

      {action && salles && (
        <Dialogues
          action={action}
          salles={salles}
          onFermer={() => setAction(null)}
          onTermine={() => {
            setAction(null);
            if (action.type === "supprimer" || action.type === "salle") setEtudiantOuvertId(null);
          }}
        />
      )}

      {dialogueListe && feuille && semaine && (
        <DialogueListe
          salle={feuille.salle}
          semaine={semaine}
          symboles={symboles}
          onFermer={() => setDialogueListe(false)}
        />
      )}
    </div>
  );
}

/** Effectif, délégué, comptes sanctionnés : ce qu'on veut savoir d'une classe d'un coup d'œil. */
function ResumeSalle({ feuille }: { feuille: FeuillePresence }) {
  const total = feuille.etudiants.length;
  const parFormation = feuille.etudiants.reduce<Record<string, number>>((acc, e) => {
    if (e.formation) acc[e.formation] = (acc[e.formation] ?? 0) + 1;
    return acc;
  }, {});
  const restreints = feuille.etudiants.filter((e) => e.statut_compte === "restreint").length;
  const bloques = feuille.etudiants.filter((e) => e.statut_compte === "bloque").length;
  const tenues = feuille.seances.filter((s) => s.statut === "tenue").length;
  const { presents, notees } = feuille.etudiants.reduce(
    (acc, e) => {
      const t = tauxDeLaSemaine(e, feuille.seances);
      return { presents: acc.presents + t.presents, notees: acc.notees + t.total };
    },
    { presents: 0, notees: 0 },
  );
  const taux = notees > 0 ? Math.round((presents / notees) * 100) : null;

  return (
    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-border bg-card px-4 py-2.5 text-[13px] text-muted-foreground shadow-xs">
      <span className="flex items-center gap-1.5">
        <DoorOpen className="size-4" />
        <span className="font-medium text-foreground">{feuille.salle.nom}</span>
        {feuille.salle.filiere && <span>· {feuille.salle.filiere}</span>}
        {feuille.salle.niveau && <span>· {feuille.salle.niveau}</span>}
      </span>
      <span className="flex items-center gap-1.5">
        <Users className="size-4" />
        <span className="font-medium text-foreground tabular-nums">{total}</span> étudiant{total > 1 ? "s" : ""}
        {Object.keys(parFormation).length > 0 && (
          <span className="flex items-center gap-1">
            {Object.entries(parFormation).map(([f, n]) => (
              <span
                key={f}
                className={cn(
                  "rounded px-1.5 py-px text-[11px] font-semibold tabular-nums",
                  f === "FM" ? "bg-warning/25 text-warning-foreground" : "bg-secondary text-secondary-foreground",
                )}
              >
                {n} {f}
              </span>
            ))}
          </span>
        )}
      </span>
      <span className="flex items-center gap-1.5">
        <Crown className="size-4 text-warning-foreground" />
        {feuille.delegue ? (
          <span className="font-medium text-foreground">{feuille.delegue.name}</span>
        ) : (
          <span>aucun délégué</span>
        )}
      </span>
      {(restreints > 0 || bloques > 0) && (
        <span className="flex items-center gap-2">
          {restreints > 0 && (
            <span className="flex items-center gap-1 text-warning-foreground">
              <ShieldOff className="size-3.5" /> {restreints} restreint{restreints > 1 ? "s" : ""}
            </span>
          )}
          {bloques > 0 && (
            <span className="flex items-center gap-1 text-destructive">
              <ShieldBan className="size-3.5" /> {bloques} bloqué{bloques > 1 ? "s" : ""}
            </span>
          )}
        </span>
      )}
      {taux !== null && (
        <span className="ml-auto tabular-nums">
          Assiduité de la semaine :{" "}
          <span
            className={cn(
              "font-semibold",
              taux >= 75 ? "text-success" : taux >= 50 ? "text-warning-foreground" : "text-destructive",
            )}
          >
            {taux} %
          </span>{" "}
          <span className="text-muted-foreground/70">
            sur {tenues} séance{tenues > 1 ? "s" : ""} tenue{tenues > 1 ? "s" : ""}
          </span>
        </span>
      )}
    </div>
  );
}

function Legende({ symboles }: { symboles: "coche" | "valeur" }) {
  const present = symboles === "valeur" ? "+1" : "✓";
  const absent = symboles === "valeur" ? "−1" : "✗";
  return (
    <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 px-1 text-[12.5px] text-muted-foreground">
      <span>
        <span className="font-display font-bold text-success">{present}</span> présent
      </span>
      <span>
        <span className="font-display font-bold text-destructive">{absent}</span> absent
      </span>
      <span>
        <span className="font-display font-bold text-muted-foreground/60">·</span> non renseigné
      </span>
      <span>
        <span className="font-display font-bold text-muted-foreground/60">–</span> appel jamais validé
      </span>
      <span className="flex items-center gap-1">
        <Crown className="size-3.5 text-warning-foreground" /> délégué
      </span>
      <span className="flex items-center gap-1">
        <span className="rounded bg-warning/25 px-1 text-[10px] font-semibold text-warning-foreground">FM</span>
        formation migrante
      </span>
    </div>
  );
}

/**
 * Les dialogues d'action attendent le modèle `User` de l'API ; la feuille
 * ne transporte que ce dont la grille a besoin. On complète avec la salle
 * affichée — ce que ces dialogues lisent réellement.
 */
function versUser(e: EtudiantFeuille, feuille: FeuillePresence): User {
  return {
    id: e.id,
    name: e.name,
    phone: e.phone,
    role: e.role,
    effective_role: e.role,
    validation_status: "approved",
    statut_compte: e.statut_compte,
    motif_statut: e.motif_statut,
    statut_modifie_le: null,
    formation: e.formation,
    salle: { id: feuille.salle.id, nom: feuille.salle.nom },
    niveau: null,
    filiere: feuille.salle.filiere ? { id: 0, nom: feuille.salle.filiere } : null,
    quota: 0,
    has_active_promotion: false,
  };
}

/** Un seul point de montage pour les dialogues d'action sur un compte. */
function Dialogues({
  action,
  salles,
  onFermer,
  onTermine,
}: {
  action: ActionEtudiant;
  salles: Salle[];
  onFermer: () => void;
  onTermine: () => void;
}) {
  const changerSalle = useChangerSalle();
  const changerStatut = useChangerStatut();
  const supprimer = useSupprimerEtudiant();
  const apres = { onSuccess: onTermine };

  switch (action.type) {
    case "salle":
      return (
        <DialogueSalle
          etudiant={action.etudiant}
          salles={salles}
          enCours={changerSalle.isPending}
          onConfirmer={(salle_id) => changerSalle.mutate({ id: action.etudiant.id, salle_id }, apres)}
          onFermer={onFermer}
        />
      );
    case "restreindre":
    case "bloquer":
      return (
        <DialogueSanction
          etudiant={action.etudiant}
          type={action.type}
          enCours={changerStatut.isPending}
          onConfirmer={(motif) =>
            changerStatut.mutate({ id: action.etudiant.id, action: action.type, motif }, apres)
          }
          onFermer={onFermer}
        />
      );
    case "retablir":
      return (
        <DialogueConfirmation
          etudiant={action.etudiant}
          type="retablir"
          enCours={changerStatut.isPending}
          onConfirmer={() => changerStatut.mutate({ id: action.etudiant.id, action: "retablir" }, apres)}
          onFermer={onFermer}
        />
      );
    case "supprimer":
      return (
        <DialogueConfirmation
          etudiant={action.etudiant}
          type="supprimer"
          enCours={supprimer.isPending}
          onConfirmer={() => supprimer.mutate(action.etudiant.id, apres)}
          onFermer={onFermer}
        />
      );
    case "presence":
      // Les présences se corrigent directement dans la grille.
      return null;
  }
}
