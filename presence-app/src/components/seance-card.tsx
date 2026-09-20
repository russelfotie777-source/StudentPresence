"use client";

import { Clock3 } from "lucide-react";
import { LigneDeTemps, PresentsEnDirect } from "@/components/vie-seance";
import type { Seance, UserRole } from "@/types/api";

export function SeanceCard({
  seance,
  children,
  featured = false,
  role,
}: {
  seance: Seance;
  children?: React.ReactNode;
  featured?: boolean;
  /** Pour la séance mise en avant : qui regarde, pour dire qui est là. */
  role?: UserRole;
}) {
  return (
    <article
      className={`session ${featured ? "session-featured" : "session-row"} ${seance.is_active ? "session-live" : ""}`}
    >
      {/* La séance en cours n'a pas d'étiquette : sa teinte, le temps qui
          avance et son bouton disent tout. La prochaine dit quand elle vient. */}
      {featured ? (
        !seance.is_active && (
          <div className="session-topline">
            <span className="session-label">
              <Clock3 size={14} /> Prochaine séance
            </span>
          </div>
        )
      ) : (
        <div className="session-time">
          <strong>{seance.heure_debut.slice(0, 5)}</strong>
          <span>{seance.heure_fin.slice(0, 5)}</span>
        </div>
      )}
      <div className="session-content">
        <h3>{seance.matiere ?? "Séance de cours"}</h3>
        <p className="session-details">
          {seance.enseignant ? `avec ${seance.enseignant}, en ${seance.salle}` : `en ${seance.salle}`}
        </p>
        {featured && <LigneDeTemps seance={seance} />}
        {featured && seance.etat_prof && (
          <p className="session-presence">
            {seance.etat_prof === "present"
              ? seance.etat_prof_par_delegue
                ? "Enseignant présent, confirmé par le délégué"
                : "Enseignant présent"
              : "Enseignant absent"}
          </p>
        )}
        {featured && role && <PresentsEnDirect seance={seance} role={role} />}
        {children && <div className="session-actions">{children}</div>}
      </div>
    </article>
  );
}
