"use client";

import { useState } from "react";
import dynamic from "next/dynamic";
import { motion } from "motion/react";
import { ArrowRight, Check, Clock3, DoorOpen, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useMe } from "@/hooks/use-auth";
import {
  useMyFormationRequests,
  useSituationMigration,
  useSubmitFormationRequest,
  useWithdrawFormationRequest,
} from "@/hooks/use-formation-requests";
import type { DemandeFormation, SituationMigration } from "@/types/api";

// three.js ne se charge que pour ceux qui verront la scène : les troisièmes années.
const SummitSculpture = dynamic(
  () => import("@/components/summit-sculpture").then((module) => module.SummitSculpture),
  { ssr: false },
);

/**
 * Migration FA → FI : l'étudiant en alternance demande à suivre les cours de
 * jour dans une salle de son département et de son niveau. Il choisit la
 * salle, l'administration valide — et c'est seulement là que son compte
 * change de salle. L'écran montre d'abord où il en est, puis ce qu'il peut
 * faire.
 */
export default function MigrationPage() {
  const { data: me } = useMe();
  const situation = useSituationMigration(me?.user.role === "Etudiant");
  const historique = useMyFormationRequests(me?.user.role === "Etudiant");

  return (
    <div className="flex flex-col gap-6">
      <motion.div
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: "easeOut" }}
      >
        <h1 className="font-display text-xl font-bold tracking-tight text-ink-900">
          Migration
        </h1>
      </motion.div>

      {situation.isLoading || !situation.data ? (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-28 w-full rounded-2xl" />
          <Skeleton className="h-40 w-full rounded-2xl" />
        </div>
      ) : (
        <>
          <Situation situation={situation.data} />
          {situation.data.demande_en_attente ? (
            <DemandeEnAttente situation={situation.data} />
          ) : situation.data.eligible ? (
            <Formulaire situation={situation.data} />
          ) : situation.data.formation === "FA" && auSommet(situation.data) ? (
            <Sommet situation={situation.data} />
          ) : (
            situation.data.formation === "FA" && (
              <p className="text-sm leading-relaxed text-ink-500">{situation.data.empechement}</p>
            )
          )}
        </>
      )}

      {historique.data && historique.data.some((d) => d.statut !== "en_attente") && (
        <Historique demandes={historique.data.filter((d) => d.statut !== "en_attente")} />
      )}
    </div>
  );
}

/** "L3" → 3 : le rang du niveau, comme le calcule l'API. */
function chiffre(niveau: string | null): number {
  const m = niveau?.match(/(\d+)/);
  return m ? Number(m[1]) : 1;
}

/** Fermé parce qu'on est au-delà du dernier niveau ouvert — pas pour une autre raison. */
function auSommet(s: SituationMigration): boolean {
  return chiffre(s.niveau) > s.niveau_max;
}

/**
 * Troisième année : il n'y a plus de salle à changer. Plutôt qu'une phrase
 * de refus, la scène du sommet — trois paliers, la bille qui monte et
 * s'installe sur le dernier — et ce que ça veut dire.
 */
function Sommet({ situation: s }: { situation: SituationMigration }) {
  const paliers = Array.from({ length: Math.max(3, chiffre(s.niveau)) }, (_, i) => `L${i + 1}`).slice(-3);

  return (
    <motion.section
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay: 0.1, ease: [0.22, 1, 0.36, 1] }}
      className="flex flex-col gap-4 rounded-2xl border border-line bg-card p-4"
    >
      <SummitSculpture niveaux={paliers} />
      <div className="flex flex-col gap-2">
        <h2 className="font-display text-lg font-semibold text-ink-900">
          {s.niveau} : le dernier palier.
        </h2>
        <p className="text-sm leading-relaxed text-ink-500">
          La migration se décide en première et deuxième année. Ici, le parcours se termine
          là où il a été construit — avec votre salle, votre promotion, et le diplôme au bout.
        </p>
      </div>
    </motion.section>
  );
}

const FORMATIONS: Record<string, string> = {
  FA: "Formation en alternance",
  FI: "Formation initiale",
  FM: "Formation initiale (migrant)",
};

/** Où l'étudiant en est aujourd'hui : formation, salle, et ce que ça veut dire pour lui. */
function Situation({ situation: s }: { situation: SituationMigration }) {
  const rattachement = [s.salle?.nom, s.departement?.code ?? s.filiere, s.niveau].filter(Boolean).join(" · ");

  return (
    <motion.section
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay: 0.05, ease: [0.22, 1, 0.36, 1] }}
      className="flex flex-col gap-3 rounded-2xl border border-line bg-card p-4"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-semibold text-ink-900">
            {s.formation ? FORMATIONS[s.formation] : "Formation non renseignée"}
          </p>
          {rattachement && <p className="mt-0.5 truncate text-sm text-ink-500">{rattachement}</p>}
        </div>
        {s.formation && (
          <span className="shrink-0 rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground">
            {s.formation}
          </span>
        )}
      </div>
      {s.formation === "FM" && (
        <p className="text-sm leading-relaxed text-ink-500">
          Vous suivez l’emploi du temps de jour de {s.salle?.nom ?? "votre salle"} et vous y
          êtes appelé comme les autres. Votre statut reste « migrant » (FM) pour
          l’administration.
        </p>
      )}
      {s.formation === "FI" && (
        <p className="text-sm leading-relaxed text-ink-500">
          Vous suivez déjà les cours de jour : la migration concerne les étudiants en
          alternance.
        </p>
      )}
      {s.formation === "FA" && s.eligible && (
        <p className="text-sm leading-relaxed text-ink-500">
          Vous pouvez demander à rejoindre une salle de jour de {s.departement?.code ?? "votre département"}{" "}
          en {s.niveau}. Votre salle ne change qu’une fois la demande validée par l’administration.
        </p>
      )}
    </motion.section>
  );
}

/** Le choix de la salle et l'envoi. */
function Formulaire({ situation: s }: { situation: SituationMigration }) {
  const envoyer = useSubmitFormationRequest();
  const [salleId, setSalleId] = useState<number | null>(s.salles.length === 1 ? s.salles[0].id : null);
  const [motif, setMotif] = useState("");

  return (
    <motion.section
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay: 0.1, ease: [0.22, 1, 0.36, 1] }}
      className="flex flex-col gap-4"
    >
      <div className="flex flex-col gap-2">
        <p className="text-sm font-semibold text-ink-900">Salle de jour souhaitée</p>
        <div role="radiogroup" aria-label="Salle de jour souhaitée" className="flex flex-col gap-2">
          {s.salles.map((salle) => {
            const choisie = salleId === salle.id;
            return (
              <button
                key={salle.id}
                type="button"
                role="radio"
                aria-checked={choisie}
                onClick={() => setSalleId(salle.id)}
                className={`flex items-center gap-3 rounded-2xl border px-4 py-3.5 text-left transition-colors ${
                  choisie ? "border-primary bg-accent" : "border-line bg-card active:bg-secondary"
                }`}
              >
                <span
                  className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${
                    choisie ? "bg-primary text-primary-foreground" : "bg-muted text-ink-500"
                  }`}
                >
                  {choisie ? <Check className="h-4 w-4" /> : <DoorOpen className="h-4 w-4" />}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-semibold text-ink-900">{salle.nom}</span>
                  <span className="block truncate text-xs text-ink-500">
                    {[
                      salle.filiere !== s.filiere ? salle.filiere : null,
                      salle.niveau,
                      `${salle.effectif} étudiant${salle.effectif === 1 ? "" : "s"}`,
                    ]
                      .filter(Boolean)
                      .join(" · ")}
                  </span>
                </span>
              </button>
            );
          })}
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="motif" className="text-sm font-semibold text-ink-900">
          Motif <span className="font-normal text-ink-500">(facultatif)</span>
        </label>
        <textarea
          id="motif"
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
          rows={3}
          placeholder="Ex. : mon contrat d’alternance s’est terminé."
          className="rounded-xl border border-input bg-card px-3.5 py-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        />
      </div>

      <Button
        className="h-12 w-full rounded-xl text-base"
        disabled={!salleId || envoyer.isPending}
        onClick={() =>
          salleId &&
          envoyer.mutate(
            { salle_cible_id: salleId, motif: motif.trim() || undefined },
            { onSuccess: () => setMotif("") },
          )
        }
      >
        {envoyer.isPending ? "Envoi…" : "Envoyer la demande"}
        {!envoyer.isPending && <ArrowRight className="h-4 w-4" />}
      </Button>
    </motion.section>
  );
}

/** Une demande déposée, que l'administration n'a pas encore traitée. */
function DemandeEnAttente({ situation: s }: { situation: SituationMigration }) {
  const retirer = useWithdrawFormationRequest();
  const demande = s.demande_en_attente!;

  return (
    <motion.section
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay: 0.1, ease: [0.22, 1, 0.36, 1] }}
      className="flex flex-col gap-3 rounded-2xl border border-warning/40 bg-warning/10 p-4"
    >
      <div className="flex items-center gap-2">
        <Clock3 className="h-4 w-4 text-warning-foreground" />
        <p className="text-sm font-semibold text-ink-900">Demande en attente</p>
      </div>
      <p className="text-sm leading-relaxed text-ink-700">
        Vers <strong>{demande.salle_cible?.nom ?? "une salle de jour"}</strong>
        {demande.date_creation && <>, déposée le {dateCourte(demande.date_creation)}</>}. Vous
        changerez de salle dans l’application dès que l’administration l’aura validée.
      </p>
      {demande.motif && <p className="text-sm text-ink-500">« {demande.motif} »</p>}
      <Button
        variant="outline"
        className="h-10 w-fit rounded-xl"
        disabled={retirer.isPending}
        onClick={() => retirer.mutate(demande.id)}
      >
        <X className="h-4 w-4" />
        {retirer.isPending ? "Retrait…" : "Retirer la demande"}
      </Button>
    </motion.section>
  );
}

const STATUTS: Record<string, { label: string; classe: string; icon: typeof Check }> = {
  acceptee: { label: "Acceptée", classe: "bg-success/15 text-success", icon: Check },
  rejetee: { label: "Rejetée", classe: "bg-destructive/10 text-destructive", icon: X },
  en_attente: { label: "En attente", classe: "bg-warning/20 text-warning-foreground", icon: Clock3 },
};

/** Les demandes déjà traitées, avec la réponse de l'administration. */
function Historique({ demandes }: { demandes: DemandeFormation[] }) {
  return (
    <section className="flex flex-col gap-2.5">
      <h2 className="text-sm font-semibold text-ink-900">Demandes précédentes</h2>
      {demandes.map((d) => {
        const statut = STATUTS[d.statut];
        return (
          <div key={d.id} className="flex flex-col gap-2 rounded-2xl border border-line bg-card p-4">
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm font-semibold text-ink-900">
                {d.salle_cible?.nom ?? "Salle de jour"}
                {d.salle_cible?.niveau && (
                  <span className="font-normal text-ink-500"> · {d.salle_cible.niveau}</span>
                )}
              </p>
              <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${statut.classe}`}>
                <statut.icon className="h-3.5 w-3.5" />
                {statut.label}
              </span>
            </div>
            <p className="text-xs text-ink-500">
              Déposée le {dateCourte(d.date_creation)}
              {d.date_traitement && <> · traitée le {dateCourte(d.date_traitement)}</>}
            </p>
            {d.commentaire_admin && (
              <p className="text-sm leading-relaxed text-ink-700">« {d.commentaire_admin} »</p>
            )}
          </div>
        );
      })}
    </section>
  );
}

function dateCourte(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "long" });
}
