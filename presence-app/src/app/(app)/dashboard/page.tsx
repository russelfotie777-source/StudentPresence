"use client";

import { useState } from "react";
import { CalendarX, MapPin } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { SeanceCard } from "@/components/seance-card";
import { CheckInDialog } from "@/components/checkin-dialog";
import { RosterDialog } from "@/components/roster-dialog";
import { PushDialog } from "@/components/push-dialog";
import { SendPositionButton } from "@/components/send-position-button";
import { useMe } from "@/hooks/use-auth";
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

export default function DashboardPage() {
  const { data: me } = useMe();
  const { data: seances, isLoading } = useTodaySeances();
  const role = me?.user.effective_role;

  const [checkInSeance, setCheckInSeance] = useState<Seance | null>(null);
  const [rosterSeance, setRosterSeance] = useState<Seance | null>(null);
  const [pushSeance, setPushSeance] = useState<Seance | null>(null);

  const today = new Date();
  const jourFr = WEEKDAY_LABELS[
    ["DIMANCHE", "LUNDI", "MARDI", "MERCREDI", "JEUDI", "VENDREDI", "SAMEDI"][today.getDay()]
  ];

  return (
    <div className="flex flex-col gap-6">
      <div>
        <p className="text-sm text-muted-foreground capitalize">
          {jourFr} {today.toLocaleDateString("fr-FR", { day: "numeric", month: "long" })}
        </p>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Bonjour, {firstName(me?.user.name)} 👋
        </h1>
      </div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {[...Array(3)].map((_, i) => (
            <Skeleton key={i} className="h-32 w-full rounded-2xl" />
          ))}
        </div>
      )}

      {!isLoading && seances?.length === 0 && (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-14 text-center">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
            <CalendarX className="h-5 w-5 text-muted-foreground" />
          </div>
          <div>
            <p className="font-medium text-foreground">Aucune séance aujourd&apos;hui</p>
            <p className="mt-1 text-sm text-muted-foreground">Profitez-en pour vous reposer.</p>
          </div>
        </div>
      )}

      <div className="flex flex-col gap-3">
        {seances?.map((seance) => (
          <SeanceCard key={seance.id} seance={seance}>
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
        ))}
      </div>

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
    <Button size="sm" className="h-10 flex-1 gap-1.5 rounded-lg" onClick={onCheckIn}>
      <MapPin className="h-4 w-4" />
      Je suis présent(e)
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
