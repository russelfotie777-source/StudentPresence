"use client";

import Link from "next/link";
import { motion } from "motion/react";
import {
  BadgeCheck,
  MessageSquareWarning,
  ArrowLeftRight,
  ArrowRight,
  CalendarRange,
  School,
  Users,
  GraduationCap,
  DoorOpen,
  BookOpen,
  CheckCircle2,
  AlertTriangle,
  TrendingDown,
} from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { useDashboard, type DashboardStats } from "@/hooks/use-dashboard";
import { cn } from "@/lib/utils";

const apparition = {
  hidden: { opacity: 0, y: 10 },
  show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: [0.22, 1, 0.36, 1] as const } },
};

export default function DashboardPage() {
  const { data, isLoading } = useDashboard();

  const aTraiter = data?.a_traiter;
  const total =
    (aTraiter?.delegues ?? 0) +
    (aTraiter?.enseignants ?? 0) +
    (aTraiter?.requetes ?? 0) +
    (aTraiter?.migrations ?? 0);

  return (
    <motion.div
      initial="hidden"
      animate="show"
      variants={{ show: { transition: { staggerChildren: 0.06 } } }}
      className="flex flex-col gap-6"
    >
      <motion.div variants={apparition} className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
            Vue d&apos;ensemble
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {isLoading
              ? "Chargement des chiffres…"
              : total > 0
                ? `${total} ${total > 1 ? "éléments attendent" : "élément attend"} votre décision.`
                : "Rien n'attend votre décision pour le moment."}
          </p>
        </div>
        {!isLoading && data && <PastilleActivite activite={data.activite} />}
      </motion.div>

      <motion.section variants={apparition} className="flex flex-col gap-3">
        <TitreSection>À traiter</TitreSection>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {isLoading || !aTraiter ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[104px] rounded-2xl" />)
          ) : (
            <>
              <CarteAction
                icon={BadgeCheck}
                valeur={aTraiter.delegues}
                label="Délégués à valider"
                href="/validations"
              />
              <CarteAction
                icon={BadgeCheck}
                valeur={aTraiter.enseignants}
                label="Enseignants à valider"
                href="/validations"
              />
              <CarteAction
                icon={MessageSquareWarning}
                valeur={aTraiter.requetes}
                label="Requêtes enseignants"
                href="/requetes"
              />
              <CarteAction
                icon={ArrowLeftRight}
                valeur={aTraiter.migrations}
                label="Migrations FA → FI"
                href="/demandes-formation"
              />
            </>
          )}
        </div>
      </motion.section>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <motion.section variants={apparition} className="flex flex-col gap-3 lg:col-span-2">
          <TitreSection>Activité</TitreSection>
          {isLoading || !data ? (
            <Skeleton className="h-[168px] rounded-2xl" />
          ) : (
            <CarteActivite activite={data.activite} />
          )}
        </motion.section>

        <motion.section variants={apparition} className="flex flex-col gap-3">
          <TitreSection>Catalogue</TitreSection>
          {isLoading || !data ? (
            <Skeleton className="h-[168px] rounded-2xl" />
          ) : (
            <CarteCatalogue catalogue={data.catalogue} />
          )}
        </motion.section>
      </div>
    </motion.div>
  );
}

function TitreSection({ children }: { children: React.ReactNode }) {
  return (
    <h2 className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
      {children}
    </h2>
  );
}

/**
 * Une carte à zéro reste affichée, mais visuellement en retrait : la faire
 * disparaître obligerait à vérifier ailleurs qu'il n'y a effectivement rien,
 * et ferait bouger la grille à chaque validation.
 */
function CarteAction({
  icon: Icon,
  valeur,
  label,
  href,
}: {
  icon: typeof BadgeCheck;
  valeur: number;
  label: string;
  href: string;
}) {
  const aTraiter = valeur > 0;

  return (
    <Link
      href={href}
      className={cn(
        "group/carte relative flex flex-col justify-between gap-3 overflow-hidden rounded-2xl border p-4 transition-all",
        aTraiter
          ? "border-border bg-card shadow-xs hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
          : "border-border/60 bg-card/50 hover:border-border",
      )}
    >
      <div className="flex items-start justify-between">
        <div
          className={cn(
            "flex size-9 items-center justify-center rounded-xl",
            aTraiter ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground",
          )}
        >
          <Icon className="size-[18px]" />
        </div>
        <ArrowRight
          className={cn(
            "size-4 -translate-x-1 opacity-0 transition-all group-hover/carte:translate-x-0 group-hover/carte:opacity-100",
            aTraiter ? "text-primary" : "text-muted-foreground",
          )}
        />
      </div>
      <div>
        <p
          className={cn(
            "font-display text-[28px] leading-none font-semibold tabular-nums",
            aTraiter ? "text-foreground" : "text-muted-foreground/60",
          )}
        >
          {valeur}
        </p>
        <p className="mt-1.5 text-[13px] text-muted-foreground">{label}</p>
      </div>
    </Link>
  );
}

function PastilleActivite({ activite }: { activite: DashboardStats["activite"] }) {
  const { seances_aujourdhui: aujourdhui } = activite;

  return (
    <div className="flex items-center gap-2 rounded-full border border-border bg-card px-3.5 py-1.5 shadow-xs">
      <span
        className={cn(
          "size-2 rounded-full",
          aujourdhui > 0 ? "animate-pulse bg-success" : "bg-muted-foreground/40",
        )}
      />
      <span className="text-[13px] text-muted-foreground">
        {aujourdhui > 0 ? (
          <>
            <span className="font-semibold text-foreground tabular-nums">{aujourdhui}</span>{" "}
            {aujourdhui > 1 ? "séances aujourd'hui" : "séance aujourd'hui"}
          </>
        ) : (
          "Aucune séance aujourd'hui"
        )}
      </span>
    </div>
  );
}

/**
 * Seuils d'appréciation du taux de présence. Un même chiffre habillé de vert
 * quelle que soit sa valeur ne renseigne sur rien : à 0 %, une coche verte
 * rassure à tort alors que c'est précisément le cas qui appelle une
 * vérification.
 */
function appreciation(taux: number) {
  if (taux >= 85) {
    return { icon: CheckCircle2, teinte: "text-success", fond: "bg-success/10", barre: "bg-success" };
  }
  if (taux >= 60) {
    return { icon: AlertTriangle, teinte: "text-warning-foreground", fond: "bg-warning/20", barre: "bg-warning" };
  }

  return { icon: TrendingDown, teinte: "text-destructive", fond: "bg-destructive/10", barre: "bg-destructive" };
}

function CarteActivite({ activite }: { activite: DashboardStats["activite"] }) {
  const { taux_presence: taux, seances_recentes, seances_presentes, jours_observes } = activite;
  const ton = taux === null ? null : appreciation(taux);
  const Icone = ton?.icon ?? CheckCircle2;

  return (
    <div className="flex h-full flex-col justify-between gap-5 rounded-2xl border border-border bg-card p-5 shadow-xs">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-[13px] text-muted-foreground">
            Taux de présence sur {jours_observes} jours
          </p>
          {taux === null ? (
            <p className="mt-2 font-display text-[32px] leading-none font-semibold text-muted-foreground/60">
              —
            </p>
          ) : (
            <p className="mt-2 font-display text-[40px] leading-none font-semibold tabular-nums text-foreground">
              {taux}
              <span className="ml-0.5 text-2xl text-muted-foreground">%</span>
            </p>
          )}
        </div>
        <div
          className={cn(
            "flex size-10 items-center justify-center rounded-xl",
            ton ? ton.fond : "bg-muted",
          )}
        >
          <Icone className={cn("size-5", ton ? ton.teinte : "text-muted-foreground")} />
        </div>
      </div>

      {taux === null ? (
        <p className="text-[13px] leading-relaxed text-muted-foreground">
          Aucune séance sur la période — rien à mesurer pour l&apos;instant. Ce n&apos;est pas
          un taux de 0 %.
        </p>
      ) : (
        <div className="flex flex-col gap-2">
          <div className="h-2 overflow-hidden rounded-full bg-muted">
            <motion.div
              initial={{ width: 0 }}
              animate={{ width: `${taux}%` }}
              transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
              className={cn("h-full rounded-full", ton?.barre)}
            />
          </div>
          <p className="text-[13px] text-muted-foreground tabular-nums">
            {seances_presentes} séances honorées sur {seances_recentes}
          </p>
        </div>
      )}

      <div className="flex flex-wrap gap-2 border-t border-border pt-4">
        <LienRapide href="/emplois-du-temps" icon={CalendarRange} label="Emplois du temps" />
        <LienRapide href="/historique" icon={School} label="Historique" />
      </div>
    </div>
  );
}

function CarteCatalogue({ catalogue }: { catalogue: DashboardStats["catalogue"] }) {
  const lignes = [
    { icon: GraduationCap, label: "Étudiants", valeur: catalogue.etudiants },
    { icon: Users, label: "Enseignants", valeur: catalogue.enseignants },
    { icon: DoorOpen, label: "Salles", valeur: catalogue.salles },
    { icon: BookOpen, label: "Matières", valeur: catalogue.matieres },
  ];

  return (
    <div className="flex h-full flex-col gap-3 rounded-2xl border border-border bg-card p-5 shadow-xs">
      <div className="flex flex-col gap-3">
        {lignes.map((ligne) => (
          <div key={ligne.label} className="flex items-center gap-3">
            <ligne.icon className="size-4 shrink-0 text-muted-foreground" />
            <span className="flex-1 text-[13px] text-muted-foreground">{ligne.label}</span>
            <span className="font-display text-[15px] font-semibold text-foreground tabular-nums">
              {ligne.valeur}
            </span>
          </div>
        ))}
      </div>
      <div className="mt-auto border-t border-border pt-4">
        <LienRapide href="/catalogue" icon={School} label="Gérer le catalogue" />
      </div>
    </div>
  );
}

function LienRapide({
  href,
  icon: Icon,
  label,
}: {
  href: string;
  icon: typeof School;
  label: string;
}) {
  return (
    <Link
      href={href}
      className="inline-flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-[12.5px] font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
    >
      <Icon className="size-3.5" />
      {label}
    </Link>
  );
}
