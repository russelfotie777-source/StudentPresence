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
                <Radio size={14} /> EN COURS
              </>
            ) : (
              <>
                <Clock3 size={14} /> PROCHAINE SÉANCE
              </>
            )}
          </span>
          <span className="session-code">GROUPE {seance.groupe}</span>
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
            <span className="session-presence">
              {seance.etat_prof === "present"
                ? "Enseignant présent"
                : seance.etat_prof === "absent"
                  ? "Enseignant absent"
                  : "En attente de l’enseignant"}
            </span>
          </div>
        )}
        {children && <div className="session-actions">{children}</div>}
      </div>
    </article>
  );
}
