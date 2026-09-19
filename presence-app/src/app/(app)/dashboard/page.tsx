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
  ArrowRight,
  CalendarCheck2,
  GraduationCap,
  UserCheck,
} from "lucide-react";
import { ZirisMark, ZirisWordmark } from "@/components/ziris-brand";
import styles from "./dashboard.module.css";
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
import { FUSEAU, useHeureDouala } from "@/hooks/use-heure";
import {
  useConfirmerEnseignant,
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

/**
 * Le prénom tel qu'on le dit, pas tel qu'il a été tapé : « RUSSEL » ou
 * « jean-paul » à l'inscription deviennent « Russel » et « Jean-Paul » dans
 * le bonjour. Le nom complet reste intact partout ailleurs.
 */
function prenom(nomComplet: string): string {
  const premier = nomComplet.trim().split(/\s+/)[0] ?? "";
  return premier
    .toLocaleLowerCase("fr")
    .split("-")
    .map((partie) => partie.charAt(0).toLocaleUpperCase("fr") + partie.slice(1))
    .join("-");
}

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
  const {
    data: stats,
    isLoading: statsLoading,
    isError: statsError,
    refetch: refetchStats,
    isFetching: statsFetching,
  } = useAttendanceStats(role === "Etudiant");
  const { data: notifications } = useNotifications();
  const [filter, setFilter] = useState<"all" | "remaining">("all");
  const [checkInSeance, setCheckInSeance] = useState<Seance | null>(null);
  const [rosterSeance, setRosterSeance] = useState<Seance | null>(null);
  const [pushSeance, setPushSeance] = useState<Seance | null>(null);
  // La date affichée est celle de Douala, servie par l'API : c'est elle qui
  // décide des séances « d'aujourd'hui », pas l'horloge du téléphone.
  const { maintenant: today } = useHeureDouala();
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
    <div className={`dashboard ${styles.dashboard}`}>
      <header className="dashboard-topbar">
        <Link
          href="/dashboard"
          className="presence-brand"
          aria-label="Ziris, accueil"
        >
          <span className="brand-mark">
            <ZirisMark size={22} />
          </span>
          <ZirisWordmark />
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
          <p className={`eyebrow ${styles.role}`}>
            {roleLabel}
            {me?.user.salle?.nom && (
              <span className={styles.classLabel}> · {me.user.salle.nom}</span>
            )}
          </p>
          <h1>
            Bonjour, {me?.user.name && prenom(me.user.name)}
            <span>.</span>
          </h1>
        </div>
        <div className={`date-stamp ${styles.date}`} title={`Heure de ${FUSEAU.split("/")[1]}`}>
          <span className={styles.dateNumber}>
            {today
              ? today.toLocaleDateString("fr-FR", { timeZone: FUSEAU, day: "2-digit" })
              : "--"}
          </span>
          <span className={styles.dateWords}>
            <strong>
              {today
                ? today.toLocaleDateString("fr-FR", { timeZone: FUSEAU, weekday: "long" })
                : "\u00a0"}
            </strong>
            <span>
              {today
                ? today.toLocaleDateString("fr-FR", {
                    timeZone: FUSEAU,
                    month: "long",
                    year: "numeric",
                  })
                : "\u00a0"}
            </span>
          </span>
        </div>
      </motion.section>
      {me?.user && <BanniereRestriction user={me.user} />}
      <div className="dashboard-columns">
        <div className="schedule-column">
          <div className="section-heading">
            <h2>Aujourd’hui</h2>
            <span className="quiet-count">
              {isLoading
                ? "…"
                : isError
                  ? "Indisponible"
                  : `${seances?.length ?? 0} séance${seances?.length === 1 ? "" : "s"}`}
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
            <div className={styles.freeDay}>
              <span className={styles.freeDayIcon}>
                <CalendarCheck2 size={25} strokeWidth={1.5} />
              </span>
              <div>
                <h3>Pas de cours aujourd’hui.</h3>
                <Link href="/historique">
                  Voir mes dernières séances <ArrowRight size={14} />
                </Link>
              </div>
            </div>
          )}
          {!isLoading && !isError && !!seances?.length && !focus && (
            <div className={styles.dayComplete}>
              <CheckCheck size={20} />
              <span>Votre journée de cours est terminée.</span>
            </div>
          )}
          {!isError && !!seances?.length && (
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
            </div>
            {statsError && role === "Etudiant" ? (
              <div className={styles.statsError} role="alert">
                <p>Votre bilan est indisponible.</p>
                <button onClick={() => refetchStats()} disabled={statsFetching}>
                  <RefreshCw size={14} /> Réessayer
                </button>
              </div>
            ) : (
              <>
                <div className={styles.attendanceOverview}>
                  <div className="attendance-visual">
                    <AttendanceSculpture
                      percent={
                        role === "Etudiant"
                          ? (stats?.taux ?? null)
                          : !isError && seances?.length
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
                        {role === "Etudiant"
                          ? statsLoading
                            ? "…"
                            : (stats?.taux ?? "—")
                          : isLoading || isError
                            ? "—"
                            : remaining}
                        {role === "Etudiant" && stats?.taux != null && (
                          <span>%</span>
                        )}
                      </strong>
                      <span>
                        {role === "Etudiant"
                          ? "de présence"
                          : "séances restantes"}
                      </span>
                    </div>
                  </div>
                  <dl className={styles.attendanceFigures}>
                    <div>
                      <dt>
                        <span className={styles.presentDot} />
                        {role === "Etudiant" ? "Présences" : "Terminées"}
                      </dt>
                      <dd>
                        {role === "Etudiant"
                          ? statsLoading
                            ? "…"
                            : (stats?.presences ?? "—")
                          : isLoading || isError
                            ? "—"
                            : (seances?.filter((s) => s.is_past).length ?? 0)}
                      </dd>
                    </div>
                    <div>
                      <dt>
                        <span className={styles.totalDot} />
                        {role === "Etudiant"
                          ? "Séances au total"
                          : "Programmées"}
                      </dt>
                      <dd>
                        {role === "Etudiant"
                          ? statsLoading
                            ? "…"
                            : (stats?.total_seances ?? "—")
                          : isLoading || isError
                            ? "—"
                            : (seances?.length ?? 0)}
                      </dd>
                    </div>
                  </dl>
                </div>
              </>
            )}
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
              <span className={styles.profileLink}>
                <GraduationCap size={16} /> Mon profil
              </span>
              <ArrowUpRight size={17} />
            </Link>
          </section>
        </aside>
      </div>
      <footer className="dashboard-footer">
        <ZirisWordmark />
        <span>IUT de Douala</span>
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
        <MapPin size={16} /> Position du délégué indisponible.
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
  const confirmer = useConfirmerEnseignant(seance.id);
  const disabled =
    !seance.is_active || seance.presences_locked || mark.isPending;
  // Règle admin : quand l'enseignant n'utilise pas l'app, le délégué peut
  // confirmer sa présence à sa place — tant que l'enseignant n'a pas
  // répondu lui-même et que le délégué ne l'a pas marqué absent.
  const peutConfirmer =
    seance.confirmation_enseignant_par_delegue === true &&
    seance.etat_prof === null &&
    seance.etat_delegue !== "absent" &&
    seance.is_active &&
    !seance.presences_locked;
  // La fin réelle du cours conditionne la paie de l'enseignant : elle se
  // relève une fois le cours commencé, tant qu'elle n'est pas posée. Si le
  // délégué oublie, la séance est clôturée à l'heure prévue par le serveur.
  const peutTerminer =
    seance.etat_delegue === "present" &&
    seance.debut_reel !== null &&
    seance.fin_reelle === null &&
    seance.is_active &&
    !seance.presences_locked;
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
        onClick={() => mark.mutate({ etat: "absent" })}
      >
        <X size={16} /> Absent
      </Button>
      {peutTerminer && (
        <Button
          variant="outline"
          className="w-full"
          disabled={mark.isPending}
          onClick={() => mark.mutate({ etat: "present", set_fin_reelle: true })}
        >
          <Clock3 size={16} /> Fin du cours
        </Button>
      )}
      {seance.debut_reel && (
        <span className="attendance-status">
          <Clock3 size={15} /> Arrivée {seance.debut_reel.slice(0, 5)}
          {seance.fin_reelle
            ? ` · fin ${seance.fin_reelle.slice(0, 5)}`
            : " · fin non relevée"}
        </span>
      )}
      {peutConfirmer && (
        <Button
          variant="outline"
          className="w-full"
          disabled={confirmer.isPending}
          onClick={() => confirmer.mutate()}
        >
          <UserCheck size={16} /> Confirmer pour l’enseignant
        </Button>
      )}
      {seance.etat_prof_par_delegue && (
        <span className="attendance-status confirmed">
          <UserCheck size={16} /> Présence de l’enseignant confirmée à sa place
        </span>
      )}
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
