"use client";

import { useState } from "react";
import { motion, type Variants } from "motion/react";
import { CalendarX, MapPin } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { SeanceCard } from "@/components/seance-card";
import { CheckInDialog } from "@/components/checkin-dialog";
import { RosterDialog } from "@/components/roster-dialog";
import { PushDialog } from "@/components/push-dialog";
import { SendPositionButton } from "@/components/send-position-button";
import { AttendanceRing } from "@/components/attendance-ring";
import { ThemeToggle } from "@/components/theme-toggle";
import { useMe } from "@/hooks/use-auth";
import { useAttendanceStats } from "@/hooks/use-attendance-stats";
import { useMarkDelegue, useMarkProf, useTodaySeances } from "@/hooks/use-seances";
import type { Seance } from "@/types/api";

const WEEKDAY_LABELS: Record<string, string> = {
  LUNDI: "lundi",
  MARDI: "mardi",
  MERCREDI: "mercredi",
  JEUDI: "jeudi",
  VENDREDI: "vendredi",
  SAMEDI: "samedi",
  DIMANCHE: "dimanche",
};

function firstName(name?: string) {
  return name?.split(" ")[0] ?? "";
}

const listVariants: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.07, delayChildren: 0.1 } },
};

const itemVariants: Variants = {
  hidden: { opacity: 0, y: 16 },
  show: { opacity: 1, y: 0, transition: { duration: 0.4, ease: [0.22, 1, 0.36, 1] } },
};

export default function DashboardPage() {
  const { data: me } = useMe();
  const { data: seances, isLoading } = useTodaySeances();
  const role = me?.user.effective_role;
  const { data: stats } = useAttendanceStats(role === "Etudiant");

  const [checkInSeance, setCheckInSeance] = useState<Seance | null>(null);
  const [rosterSeance, setRosterSeance] = useState<Seance | null>(null);
  const [pushSeance, setPushSeance] = useState<Seance | null>(null);

  const today = new Date();
  const jourFr = WEEKDAY_LABELS[
    ["DIMANCHE", "LUNDI", "MARDI", "MERCREDI", "JEUDI", "VENDREDI", "SAMEDI"][today.getDay()]
  ];

  return (
    <div className="flex flex-col gap-6">
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: "easeOut" }}
        className="flex items-start justify-between"
      >
        <div>
          <p className="mb-1.5 text-[12.5px] font-semibold uppercase tracking-wide text-ink-300">
            {jourFr} {today.toLocaleDateString("fr-FR", { day: "numeric", month: "long" })}
          </p>
          <h1 className="font-display text-[28px] font-bold leading-none tracking-tight text-ink-900">
            Bonjour,
            <br />
            {firstName(me?.user.name)}.
          </h1>
        </div>
        <div className="flex flex-col items-end gap-2.5">
          <ThemeToggle />
          {stats?.taux !== null && stats?.taux !== undefined && (
            <AttendanceRing percent={stats.taux} />
          )}
        </div>
      </motion.div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {[...Array(3)].map((_, i) => (
            <Skeleton key={i} className="h-32 w-full rounded-2xl" />
          ))}
        </div>
      )}

      {!isLoading && seances?.length === 0 && (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-line py-14 text-center">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-surface-2">
            <CalendarX className="h-5 w-5 text-ink-300" />
          </div>
          <div>
            <p className="font-medium text-ink-900">Aucune séance aujourd&apos;hui</p>
            <p className="mt-1 text-sm text-ink-500">Profitez-en pour vous reposer.</p>
          </div>
        </div>
      )}

      {!isLoading && seances && seances.length > 0 && (
        <div className="flex items-baseline justify-between">
          <h2 className="font-display text-lg font-bold tracking-tight text-ink-900">
            Aujourd&apos;hui
          </h2>
          <span className="text-[12.5px] font-semibold text-ink-300">
            {seances.length} séance{seances.length > 1 ? "s" : ""}
          </span>
        </div>
      )}

      <motion.div
        variants={listVariants}
        initial="hidden"
        animate="show"
        className="flex flex-col gap-3"
      >
        {seances?.map((seance) => (
          <motion.div key={seance.id} variants={itemVariants}>
            <SeanceCard seance={seance}>
              {role === "Etudiant" && (
                <StudentActions seance={seance} onCheckIn={() => setCheckInSeance(seance)} />
              )}
              {role === "Delegue" && (
                <DelegateActions
                  seance={seance}
                  onConfirmRoster={() => setRosterSeance(seance)}
                />
              )}
              {role === "Enseignant" && (
                <TeacherActions seance={seance} onPush={() => setPushSeance(seance)} />
              )}
            </SeanceCard>
          </motion.div>
        ))}
      </motion.div>

      {checkInSeance && (
        <CheckInDialog
          seance={checkInSeance}
          open={!!checkInSeance}
          onOpenChange={(open) => !open && setCheckInSeance(null)}
        />
      )}
      {rosterSeance && (
        <RosterDialog
          seance={rosterSeance}
          open={!!rosterSeance}
          onOpenChange={(open) => !open && setRosterSeance(null)}
        />
      )}
      {pushSeance && (
        <PushDialog
          seance={pushSeance}
          open={!!pushSeance}
          onOpenChange={(open) => !open && setPushSeance(null)}
        />
      )}
    </div>
  );
}

function StudentActions({
  seance,
  onCheckIn,
}: {
  seance: Seance;
  onCheckIn: () => void;
}) {
  if (seance.presences_locked || seance.ma_presence === "present") {
    return (
      <span className="text-sm font-medium text-success">
        {seance.presences_locked ? "Roster verrouillé par le délégué" : "Présence confirmée"}
      </span>
    );
  }

  if (seance.is_past) {
    return <span className="text-sm text-muted-foreground">Séance terminée</span>;
  }

  if (!seance.is_active) {
    return (
      <span className="text-sm text-muted-foreground">
        Pointage disponible pendant la séance
      </span>
    );
  }

  return (
    <motion.div whileTap={{ scale: 0.96 }} className="flex-1">
      <Button size="sm" className="h-10 w-full gap-1.5 rounded-lg" onClick={onCheckIn}>
        <MapPin className="h-4 w-4" />
        Je suis présent(e)
      </Button>
    </motion.div>
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
  const disabled = !seance.is_active || seance.presences_locked;

  return (
    <>
      {seance.is_active && !seance.presences_locked && (
        <SendPositionButton seanceId={seance.id} alreadySent={seance.position_envoyee} />
      )}
      <Button
        size="sm"
        variant={seance.etat_delegue === "present" ? "default" : "outline"}
        className="h-10 flex-1 rounded-lg"
        disabled={disabled || seance.etat_delegue === "present"}
        onClick={() => mark.mutate({ etat: "present", set_debut_reel: true })}
      >
        Présent
      </Button>
      <Button
        size="sm"
        variant={seance.etat_delegue === "absent" ? "destructive" : "outline"}
        className="h-10 flex-1 rounded-lg"
        disabled={disabled || seance.etat_delegue === "absent"}
        onClick={() => mark.mutate({ etat: "absent", set_fin_reelle: true })}
      >
        Absent
      </Button>
      {!seance.presences_locked && (
        <Button
          size="sm"
          variant="secondary"
          className="h-10 w-full rounded-lg"
          onClick={onConfirmRoster}
        >
          Confirmer la liste de présence
        </Button>
      )}
    </>
  );
}

function TeacherActions({ seance, onPush }: { seance: Seance; onPush: () => void }) {
  const mark = useMarkProf(seance.id);
  const disabled = !seance.is_active;

  return (
    <>
      <Button
        size="sm"
        variant={seance.etat_prof === "present" ? "default" : "outline"}
        className="h-10 flex-1 rounded-lg"
        disabled={disabled || seance.etat_prof === "present"}
        onClick={() => mark.mutate("present")}
      >
        Présent
      </Button>
      <Button
        size="sm"
        variant={seance.etat_prof === "absent" ? "destructive" : "outline"}
        className="h-10 flex-1 rounded-lg"
        disabled={disabled || seance.etat_prof === "absent"}
        onClick={() => mark.mutate("absent")}
      >
        Absent
      </Button>
      <Button
        size="sm"
        variant="secondary"
        className="h-10 w-full rounded-lg"
        onClick={onPush}
      >
        {seance.push ? `Effectif déclaré : ${seance.push.etudiants_presents}` : "Déclarer l'effectif"}
      </Button>
    </>
  );
}
