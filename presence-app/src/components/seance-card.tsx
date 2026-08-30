import { Lock } from "lucide-react";
import { cn } from "@/lib/utils";
import type { Seance } from "@/types/api";

function EtatDot({ etat }: { etat: string | null }) {
  if (!etat) return <span className="h-1.5 w-1.5 rounded-full bg-border" />;
  return (
    <span
      className={cn(
        "h-1.5 w-1.5 rounded-full",
        etat === "present" ? "bg-success" : "bg-destructive",
      )}
    />
  );
}

export function SeanceCard({
  seance,
  children,
}: {
  seance: Seance;
  children?: React.ReactNode;
}) {
  return (
    <div
      className={cn(
        "flex flex-col gap-3 rounded-2xl border bg-card p-4 transition-shadow",
        seance.is_active
          ? "border-primary/40 shadow-[0_2px_16px_-4px] shadow-primary/20"
          : "border-border shadow-sm",
      )}
    >
      <div className="flex items-start gap-3">
        <div
          className={cn(
            "flex w-14 shrink-0 flex-col items-center rounded-xl py-2 text-center",
            seance.is_active ? "bg-primary text-primary-foreground" : "bg-muted text-foreground",
          )}
        >
          <span className="text-sm font-semibold leading-none">{seance.heure_debut}</span>
          <span className="mt-1 text-[10px] opacity-70">{seance.heure_fin}</span>
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <p className="truncate font-semibold text-foreground">
              {seance.matiere ?? "Séance"}
            </p>
            {seance.is_active && (
              <span className="flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary">
                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-primary" />
                En cours
              </span>
            )}
          </div>
          <p className="truncate text-sm text-muted-foreground">{seance.salle}</p>
          <p className="truncate text-xs text-muted-foreground/80">{seance.enseignant}</p>
        </div>
      </div>

      <div className="flex items-center gap-3 border-t border-border pt-3 text-xs text-muted-foreground">
        <span className="flex items-center gap-1.5">
          <EtatDot etat={seance.etat_delegue} /> Délégué
        </span>
        <span className="flex items-center gap-1.5">
          <EtatDot etat={seance.etat_prof} /> Enseignant
        </span>
        {seance.presences_locked && (
          <span className="ml-auto flex items-center gap-1 text-muted-foreground/80">
            <Lock className="h-3 w-3" /> Verrouillée
          </span>
        )}
      </div>

      {children && <div className="flex flex-wrap gap-2">{children}</div>}
    </div>
  );
}
