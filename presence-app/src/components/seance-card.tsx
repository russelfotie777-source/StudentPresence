import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import type { Seance } from "@/types/api";

const ETAT_STYLES: Record<string, string> = {
  present: "bg-green-100 text-green-800",
  absent: "bg-red-100 text-red-800",
};

function EtatBadge({ label, etat }: { label: string; etat: string | null }) {
  if (!etat) return <Badge variant="outline">{label} : —</Badge>;
  return <Badge className={ETAT_STYLES[etat]}>{label} : {etat === "present" ? "Présent" : "Absent"}</Badge>;
}

export function SeanceCard({
  seance,
  children,
}: {
  seance: Seance;
  children?: React.ReactNode;
}) {
  return (
    <Card className={seance.is_active ? "border-violet-400 shadow-md" : undefined}>
      <CardContent className="flex flex-col gap-3 py-4">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="font-semibold text-zinc-900">{seance.matiere ?? "Séance"}</p>
            <p className="text-sm text-zinc-500">
              {seance.salle} · {seance.heure_debut}–{seance.heure_fin}
            </p>
            <p className="text-xs text-zinc-400">{seance.enseignant}</p>
          </div>
          {seance.is_active && (
            <Badge className="bg-violet-600 text-white">En cours</Badge>
          )}
          {seance.presences_locked && <Badge variant="secondary">Verrouillée</Badge>}
        </div>

        <div className="flex flex-wrap gap-2">
          <EtatBadge label="Délégué" etat={seance.etat_delegue} />
          <EtatBadge label="Prof" etat={seance.etat_prof} />
        </div>

        {children && <div className="flex flex-wrap gap-2 pt-1">{children}</div>}
      </CardContent>
    </Card>
  );
}
