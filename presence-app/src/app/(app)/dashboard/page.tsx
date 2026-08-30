"use client";

import { useState } from "react";
import Link from "next/link";
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

export default function DashboardPage() {
  const { data: me } = useMe();
  const { data: seances, isLoading } = useTodaySeances();
  const role = me?.user.effective_role;

  const [checkInSeance, setCheckInSeance] = useState<Seance | null>(null);
  const [rosterSeance, setRosterSeance] = useState<Seance | null>(null);
  const [pushSeance, setPushSeance] = useState<Seance | null>(null);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-zinc-900">Aujourd&apos;hui</h1>
        {role === "Enseignant" && (
          <nav className="flex gap-3 text-sm">
            <Link href="/salaire" className="text-violet-700 underline">
              Salaire
            </Link>
            <Link href="/requetes" className="text-violet-700 underline">
              Requêtes
            </Link>
            <Link href="/promotion" className="text-violet-700 underline">
              Promotion
            </Link>
          </nav>
        )}
      </div>

      {isLoading && (
        <div className="flex flex-col gap-3">
          {[...Array(3)].map((_, i) => (
            <Skeleton key={i} className="h-28 w-full rounded-xl" />
          ))}
        </div>
      )}

      {!isLoading && seances?.length === 0 && (
        <p className="mt-8 text-center text-sm text-zinc-500">
          Aucune séance aujourd&apos;hui.
        </p>
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
      <span className="text-sm font-medium text-green-700">
        {seance.presences_locked ? "Roster verrouillé par le délégué" : "Présence confirmée"}
      </span>
    );
  }

  if (seance.is_past) {
    return <span className="text-sm text-zinc-400">Séance terminée</span>;
  }

  if (!seance.is_active) {
    return <span className="text-sm text-zinc-400">Pointage disponible pendant la séance</span>;
  }

  return (
    <Button size="sm" onClick={onCheckIn}>
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
        disabled={disabled || seance.etat_delegue === "present"}
        onClick={() => mark.mutate({ etat: "present", set_debut_reel: true })}
      >
        Présent
      </Button>
      <Button
        size="sm"
        variant={seance.etat_delegue === "absent" ? "destructive" : "outline"}
        disabled={disabled || seance.etat_delegue === "absent"}
        onClick={() => mark.mutate({ etat: "absent", set_fin_reelle: true })}
      >
        Absent
      </Button>
      {!seance.presences_locked && (
        <Button size="sm" variant="secondary" onClick={onConfirmRoster}>
          Confirmer la liste
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
        disabled={disabled || seance.etat_prof === "present"}
        onClick={() => mark.mutate("present")}
      >
        Présent
      </Button>
      <Button
        size="sm"
        variant={seance.etat_prof === "absent" ? "destructive" : "outline"}
        disabled={disabled || seance.etat_prof === "absent"}
        onClick={() => mark.mutate("absent")}
      >
        Absent
      </Button>
      <Button size="sm" variant="secondary" onClick={onPush}>
        {seance.push ? `Effectif : ${seance.push.etudiants_presents}` : "Déclarer effectif"}
      </Button>
    </>
  );
}
