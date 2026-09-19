"use client";

import { Check, Clock3, MapPin, Radio, UserRound } from "lucide-react";
import type { Seance } from "@/types/api";

export function SeanceCard({
  seance,
  children,
  featured = false,
}: {
  seance: Seance;
  children?: React.ReactNode;
  featured?: boolean;
}) {
  return (
    <article
      className={`session ${featured ? "session-featured" : "session-row"} ${seance.is_active ? "session-live" : ""}`}
    >
      {featured ? (
        <div className="session-topline">
          <span className="session-label">
            {seance.is_active ? (
              <>
                <Radio size={14} /> En cours
              </>
            ) : (
              <>
                <Clock3 size={14} /> Prochaine séance
              </>
            )}
          </span>
        </div>
      ) : (
        <div className="session-time">
          <strong>{seance.heure_debut.slice(0, 5)}</strong>
          <span>{seance.heure_fin.slice(0, 5)}</span>
          <span className={`timeline-dot ${seance.is_past ? "done" : ""}`}>
            {seance.is_past && <Check size={10} />}
          </span>
        </div>
      )}
      <div className="session-content">
        <h3>{seance.matiere ?? "Séance de cours"}</h3>
        <div className="session-details">
          <span>
            <MapPin size={14} />
            {seance.salle}
          </span>
          <span>
            <UserRound size={14} />
            {seance.enseignant}
          </span>
        </div>
        {featured && (
          <div className="session-hours">
            <Clock3 size={16} />
            <span>
              {seance.heure_debut.slice(0, 5)}{" "}
              <span className="time-separator">→</span>{" "}
              {seance.heure_fin.slice(0, 5)}
            </span>
            {seance.etat_prof && (
              <span className="session-presence">
                {seance.etat_prof === "present"
                  ? seance.etat_prof_par_delegue
                    ? "Enseignant présent (confirmé par le délégué)"
                    : "Enseignant présent"
                  : "Enseignant absent"}
              </span>
            )}
          </div>
        )}
        {children && <div className="session-actions">{children}</div>}
      </div>
    </article>
  );
}
