import { Lock } from "lucide-react";
import { cn } from "@/lib/utils";
import { GrainOverlay } from "@/components/grain-overlay";
import type { Seance } from "@/types/api";

function EtatDot({ etat }: { etat: string | null }) {
  if (!etat) return <span className="h-1.5 w-1.5 rounded-full bg-line" />;
  return (
    <span
      className={cn(
        "h-1.5 w-1.5 rounded-full",
        etat === "present" ? "bg-emerald-500" : "bg-rose-500",
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
  if (seance.is_active) {
    return (
      <div className="flex flex-col gap-3">
        {/* Carte-billet : le signature moment du système */}
        <div className="relative flex overflow-visible rounded-[22px] bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-[0_20px_40px_-14px_rgba(79,70,229,.5)]">
          <GrainOverlay className="rounded-[22px]" />

          <div className="relative min-w-0 flex-1 p-4">
            <div className="mb-2 flex items-center gap-2">
              <span className="relative flex h-1.5 w-1.5 rounded-full bg-white">
                <span className="absolute inset-[-3px] rounded-full bg-white/50" />
              </span>
              <span className="text-[10.5px] font-bold uppercase tracking-wider text-white/85">
                En cours
              </span>
            </div>
            <p className="truncate font-display text-[15px] font-bold text-white">
              {seance.matiere ?? "Séance"}
            </p>
            <p className="truncate text-xs text-white/75">
              {seance.salle} &middot; {seance.enseignant}
            </p>
          </div>

          <div className="relative w-0 shrink-0">
            <div className="absolute inset-y-1.5 -left-px border-l-2 border-dashed border-white/40" />
            <div className="absolute -top-2 -left-2 h-4 w-4 rounded-full bg-background" />
            <div className="absolute -bottom-2 -left-2 h-4 w-4 rounded-full bg-background" />
          </div>

          <div className="relative flex w-10 shrink-0 items-center justify-center">
            <span
              className="whitespace-nowrap text-[10px] font-bold tracking-wider text-white/80"
              style={{ writingMode: "vertical-rl", transform: "rotate(180deg)" }}
            >
              {seance.heure_debut.slice(0, 5)}
            </span>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2 px-1 text-xs text-ink-500">
          <span className="flex items-center gap-1.5">
            <EtatDot etat={seance.etat_delegue} /> Délégué
          </span>
          <span className="flex items-center gap-1.5">
            <EtatDot etat={seance.etat_prof} /> Enseignant
          </span>
          {seance.presences_locked && (
            <span className="ml-auto flex items-center gap-1">
              <Lock className="h-3 w-3" /> Verrouillée
            </span>
          )}
        </div>

        {children && <div className="flex flex-wrap gap-2">{children}</div>}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3 rounded-2xl border border-line bg-card p-4 shadow-sm">
      <div className="flex items-start gap-3">
        <div className="flex w-[52px] shrink-0 flex-col items-center rounded-xl bg-surface-2 py-2 text-center">
          <span className="font-display text-[15px] font-bold leading-none text-ink-900">
            {seance.heure_debut.slice(0, 5)}
          </span>
          <span className="mt-1 text-[10px] text-ink-300">{seance.heure_fin.slice(0, 5)}</span>
        </div>

        <div className="min-w-0 flex-1">
          <p className="truncate font-semibold text-ink-900">{seance.matiere ?? "Séance"}</p>
          <p className="truncate text-sm text-ink-500">{seance.salle}</p>
          <p className="truncate text-xs text-ink-300">{seance.enseignant}</p>
        </div>
      </div>

      <div className="flex items-center gap-3 border-t border-line pt-3 text-xs text-ink-500">
        <span className="flex items-center gap-1.5">
          <EtatDot etat={seance.etat_delegue} /> Délégué
        </span>
        <span className="flex items-center gap-1.5">
          <EtatDot etat={seance.etat_prof} /> Enseignant
        </span>
        {seance.presences_locked && (
          <span className="ml-auto flex items-center gap-1 text-ink-300">
            <Lock className="h-3 w-3" /> Verrouillée
          </span>
        )}
      </div>

      {children && <div className="flex flex-wrap gap-2">{children}</div>}
    </div>
  );
}
