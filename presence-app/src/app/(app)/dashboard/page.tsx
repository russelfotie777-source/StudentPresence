"use client";

import { useState } from "react";
import Link from "next/link";
import Image from "next/image";
import dynamic from "next/dynamic";
import { motion } from "motion/react";
import {
  ArrowUpRight,
  Bell,
  CalendarDays,
  Check,
  CheckCheck,
  Clock3,
  MapPin,
  RefreshCw,
  Users,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { SeanceCard } from "@/components/seance-card";
import { CheckInDialog } from "@/components/checkin-dialog";
import { BanniereRestriction } from "@/components/banniere-restriction";
import { RosterDialog } from "@/components/roster-dialog";
import { PushDialog } from "@/components/push-dialog";
import { SendPositionButton } from "@/components/send-position-button";
import { ThemeToggle } from "@/components/theme-toggle";
import { useMe } from "@/hooks/use-auth";
import { useNotifications } from "@/hooks/use-notifications";
import { useAttendanceStats } from "@/hooks/use-attendance-stats";
import {
  useMarkDelegue,
  useMarkProf,
  useTodaySeances,
} from "@/hooks/use-seances";
import type { Seance } from "@/types/api";

const AttendanceSculpture = dynamic(
  () =>
    import("@/components/attendance-sculpture").then(
      (m) => m.AttendanceSculpture,
    ),
  { ssr: false },
);

export default function DashboardPage() {
  const { data: me } = useMe();
  const {
    data: seances,
    isLoading,
    isError,
    refetch,
    isFetching,
  } = useTodaySeances();
  const role = me?.user.effective_role;
  const { data: stats } = useAttendanceStats(role === "Etudiant");
  const { data: notifications } = useNotifications();
  const [filter, setFilter] = useState<"all" | "remaining">("all");
  const [checkInSeance, setCheckInSeance] = useState<Seance | null>(null);
  const [rosterSeance, setRosterSeance] = useState<Seance | null>(null);
  const [pushSeance, setPushSeance] = useState<Seance | null>(null);
  const today = new Date();
  const active = seances?.find((s) => s.is_active);
  const focus = active ?? seances?.find((s) => !s.is_past && !s.is_active);
  const remaining = seances?.filter((s) => !s.is_past).length ?? 0;
  const shown = seances?.filter(
    (s) => s.id !== focus?.id && (filter === "all" || !s.is_past),
  );
  const roleLabel =
    role === "Delegue"
      ? "Délégué"
      : role === "Enseignant"
        ? "Enseignant"
        : "Étudiant";
  function actions(seance: Seance) {
    if (role === "Etudiant")
      return (
        <StudentActions
          seance={seance}
          restreint={me?.user.statut_compte === "restreint"}
          onCheckIn={() => setCheckInSeance(seance)}
        />
      );
    if (role === "Delegue")
      return (
        <DelegateActions
          seance={seance}
          onConfirmRoster={() => setRosterSeance(seance)}
        />
      );
    if (role === "Enseignant")
      return (
        <TeacherActions seance={seance} onPush={() => setPushSeance(seance)} />
      );
    return null;
  }
  return (
    <div className="dashboard">
      <header className="dashboard-topbar">
        <Link
          href="/dashboard"
          className="presence-brand"
          aria-label="Présence, accueil"
        >
          <span className="brand-mark">
            <CheckCheck size={22} />
          </span>
          présence<span className="brand-period">.</span>
        </Link>
        <div className="topbar-tools">
          <ThemeToggle />
          <Link
            href="/profil"
            className="icon-control notification-control"
            title="Notifications"
            aria-label={`Notifications, ${notifications?.non_lues ?? 0} non lues`}
          >
            <Bell size={19} />
            {(notifications?.non_lues ?? 0) > 0 && (
              <span className="notification-dot" />
            )}
          </Link>
        </div>
      </header>
      <motion.section
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="welcome-section"
      >
        <div className="welcome-copy">
          <p className="eyebrow">VOTRE ESPACE · {roleLabel}</p>
          <h1>
            Bonjour, {me?.user.name.split(" ")[0]}
            <span className="brand-period">.</span>
          </h1>
          <p className="welcome-subtitle">
            {isLoading
              ? "Votre journée se prépare."
              : active
                ? "Votre prochain geste compte."
                : remaining
                  ? "Une nouvelle journée à construire."
                  : "Votre journée, à votre rythme."}
          </p>
        </div>
        <div className="date-stamp">
          <CalendarDays size={17} />
          <span>
            {today.toLocaleDateString("fr-FR", {
              weekday: "long",
              day: "numeric",
              month: "long",
            })}
          </span>
        </div>
      </motion.section>
      {me?.user && <BanniereRestriction user={me.user} />}
      <div className="dashboard-columns">
        <div className="schedule-column">
          <div className="section-heading">
            <h2>Votre journée</h2>
            <span className="quiet-count">
              {isLoading ? "…" : `${seances?.length ?? 0} séances`}
            </span>
          </div>
          {isLoading && (
            <div
              aria-label="Chargement des séances"
              className="flex flex-col gap-4"
            >
              <Skeleton className="h-64 rounded-lg" />
              <Skeleton className="h-24 rounded-lg" />
            </div>
          )}
          {isError && (
            <div className="dashboard-empty" role="alert">
              <CalendarDays />
              <h3>Votre agenda est indisponible</h3>
              <p>La connexion n’a pas abouti.</p>
              <Button
                variant="outline"
                onClick={() => refetch()}
                disabled={isFetching}
              >
                <RefreshCw size={16} /> Réessayer
              </Button>
            </div>
          )}
          {!isLoading && !isError && focus && (
            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.45 }}
            >
              <SeanceCard seance={focus} featured>
                {actions(focus)}
              </SeanceCard>
            </motion.div>
          )}
          {!isLoading && !isError && seances?.length === 0 && (
            <div className="dashboard-empty">
              <CalendarDays size={32} />
              <h3>Une journée sans cours</h3>
              <p>Aucune séance n’est programmée aujourd’hui.</p>
              <Link href="/historique">
                Consulter mon historique <ArrowUpRight size={16} />
              </Link>
            </div>
          )}
          {!!seances?.length && (
            <>
              <div className="agenda-heading">
                <h3>Au programme</h3>
                <div
                  className="agenda-filter"
                  role="group"
                  aria-label="Filtrer les séances"
                >
                  <button
                    aria-pressed={filter === "all"}
                    onClick={() => setFilter("all")}
                  >
                    Tout
                  </button>
                  <button
                    aria-pressed={filter === "remaining"}
                    onClick={() => setFilter("remaining")}
                  >
                    À venir
                  </button>
                </div>
              </div>
              <div className="agenda-list">
                {shown?.map((seance, index) => (
                  <motion.div
                    key={seance.id}
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: index * 0.05 }}
                  >
                    <SeanceCard seance={seance}>{actions(seance)}</SeanceCard>
                  </motion.div>
                ))}
                {shown?.length === 0 && (
                  <p className="agenda-done">
                    <Check size={16} /> Aucune autre séance à afficher.
                  </p>
                )}
              </div>
            </>
          )}
        </div>
        <aside className="insights-column">
          <section className="attendance-section">
            <div className="section-heading">
              <h2>
                {role === "Etudiant" ? "Votre assiduité" : "En un regard"}
              </h2>
              <span className="eyebrow">
                {role === "Etudiant" ? "GLOBAL" : "AUJOURD’HUI"}
              </span>
            </div>
            <div className="attendance-visual">
              <AttendanceSculpture
                percent={
                  role === "Etudiant"
                    ? (stats?.taux ?? null)
                    : seances?.length
                      ? Math.round(
                          (seances.filter((s) => s.is_past).length /
                            seances.length) *
                            100,
                        )
                      : null
                }
              />
              <div className="attendance-value">
                <strong>
                  {role === "Etudiant" ? (stats?.taux ?? "—") : remaining}
                  {role === "Etudiant" && stats?.taux != null && <span>%</span>}
                </strong>
                <span>
                  {role === "Etudiant" ? "de présence" : "séances restantes"}
                </span>
              </div>
            </div>
            <div className="attendance-summary">
              <span className="metric-marker" />
              <p>
                {role === "Etudiant" ? (
                  stats ? (
                    <>
                      <strong>{stats.presences}</strong> présences sur{" "}
                      <strong>{stats.total_seances}</strong> séances
                    </>
                  ) : (
                    "Aucune statistique disponible"
                  )
                ) : (
                  <>
                    <strong>{seances?.length ?? 0}</strong> séances programmées
                    aujourd’hui
                  </>
                )}
              </p>
            </div>
            <Link href="/historique" className="text-link">
              Voir mon historique <ArrowUpRight size={17} />
            </Link>
          </section>
          <section className="academic-section">
            <div className="academic-heading">
              <Image
                src="/iut-douala.png"
                alt="IUT de Douala"
                width={42}
                height={42}
              />
              <div>
                <p className="eyebrow">MON ÉTABLISSEMENT</p>
                <h3>IUT de Douala</h3>
              </div>
            </div>
            <dl>
              <div>
                <dt>Filière</dt>
                <dd>{me?.user.filiere?.nom ?? "Non renseignée"}</dd>
              </div>
              <div>
                <dt>{role === "Enseignant" ? "Statut" : "Classe"}</dt>
                <dd>
                  {role === "Enseignant"
                    ? roleLabel
                    : (me?.user.salle?.nom ?? "Non renseignée")}
                </dd>
              </div>
            </dl>
            <Link href="/profil" className="text-link">
              Mon profil <ArrowUpRight size={17} />
            </Link>
          </section>
        </aside>
      </div>
      <footer className="dashboard-footer">
        <CheckCheck size={14} /> Présence · Chaque séance compte.
      </footer>
      {checkInSeance && (
        <CheckInDialog
          seance={checkInSeance}
          open
          onOpenChange={(open) => !open && setCheckInSeance(null)}
        />
      )}
      {rosterSeance && (
        <RosterDialog
          seance={rosterSeance}
          open
          onOpenChange={(open) => !open && setRosterSeance(null)}
        />
      )}
      {pushSeance && (
        <PushDialog
          seance={pushSeance}
          open
          onOpenChange={(open) => !open && setPushSeance(null)}
        />
      )}
    </div>
  );
}

function StudentActions({
  seance,
  restreint,
  onCheckIn,
}: {
  seance: Seance;
  restreint: boolean;
  onCheckIn: () => void;
}) {
  if (seance.ma_presence === "present")
    return (
      <span className="attendance-status confirmed">
        <CheckCheck size={17} /> Présence confirmée
      </span>
    );
  if (seance.presences_locked)
    return (
      <span className="attendance-status">
        <Check size={16} />{" "}
        {seance.ma_presence === "absent"
          ? "Absence enregistrée"
          : "Liste de présence validée"}
      </span>
    );
  if (seance.is_past)
    return (
      <span className="attendance-status">
        <Clock3 size={15} /> Séance terminée
      </span>
    );
  if (!seance.is_active)
    return (
      <span className="attendance-status">
        <Clock3 size={15} /> Pointage à l’ouverture de la séance
      </span>
    );
  if (restreint)
    return (
      <span className="attendance-status">
        Pointage indisponible : compte restreint
      </span>
    );
  if (!seance.position_envoyee)
    return (
      <span className="attendance-status">
        <MapPin size={16} /> Position du délégué indisponible. Faites constater
        votre présence lors de l’appel.
      </span>
    );
  return (
    <Button className="checkin-primary" onClick={onCheckIn}>
      <MapPin size={18} /> Je suis présent(e)
      <ArrowUpRight size={19} className="ml-auto" />
    </Button>
  );
}
function DelegateActions({
  seance,
  onConfirmRoster,
}: {
  seance: Seance;
  onConfirmRoster: () => void;
}) {
  const mark = useMarkDelegue(seance.id);
  const disabled =
    !seance.is_active || seance.presences_locked || mark.isPending;
  return (
    <div className="role-actions">
      {seance.is_active && !seance.presences_locked && (
        <SendPositionButton
          seanceId={seance.id}
          alreadySent={seance.position_envoyee}
          maxAccuracy={seance.geolocation?.max_position_accuracy_meters}
          onManualValidation={onConfirmRoster}
        />
      )}
      <Button
        variant={seance.etat_delegue === "present" ? "success" : "outline"}
        disabled={disabled || seance.etat_delegue === "present"}
        onClick={() => mark.mutate({ etat: "present", set_debut_reel: true })}
      >
        <Check size={16} /> Présent
      </Button>
      <Button
        variant={seance.etat_delegue === "absent" ? "destructive" : "outline"}
        disabled={disabled || seance.etat_delegue === "absent"}
        onClick={() => mark.mutate({ etat: "absent", set_fin_reelle: true })}
      >
        <X size={16} /> Absent
      </Button>
      {!seance.presences_locked && (
        <Button
          variant="secondary"
          className="w-full"
          onClick={onConfirmRoster}
        >
          <Users size={16} /> Confirmer la liste
        </Button>
      )}
    </div>
  );
}
function TeacherActions({
  seance,
  onPush,
}: {
  seance: Seance;
  onPush: () => void;
}) {
  const mark = useMarkProf(seance.id);
  const disabled = !seance.is_active || mark.isPending;
  return (
    <div className="role-actions">
      <Button
        variant={seance.etat_prof === "present" ? "success" : "outline"}
        disabled={disabled || seance.etat_prof === "present"}
        onClick={() => mark.mutate("present")}
      >
        <Check size={16} /> Présent
      </Button>
      <Button
        variant={seance.etat_prof === "absent" ? "destructive" : "outline"}
        disabled={disabled || seance.etat_prof === "absent"}
        onClick={() => mark.mutate("absent")}
      >
        <X size={16} /> Absent
      </Button>
      <Button variant="secondary" className="w-full" onClick={onPush}>
        <Users size={16} />
        {seance.push
          ? `Effectif déclaré : ${seance.push.etudiants_presents}`
          : "Déclarer l’effectif"}
      </Button>
    </div>
  );
}
