"use client";

import { motion } from "motion/react";
import type { Seance, UserRole } from "@/types/api";

/*
 * La journée est finie : plutôt qu'une phrase, on la montre. La journée
 * tient sur une ligne — chaque séance à sa place dans le temps, pleine
 * quand on y était — et se relit dessous, séance par séance, avec ce
 * qu'il en reste. C'est la carte verte du jour, qui prend la suite de la
 * séance en cours une fois que tout est passé.
 */

type Resultat = "oui" | "non" | "sans";

function minutes(heure: string): number {
  const [h, m] = heure.split(":").map(Number);
  return h * 60 + m;
}

function resultat(seance: Seance, role: UserRole): Resultat {
  const etat = role === "Etudiant" ? seance.ma_presence : seance.etat_final;
  return etat === "present" ? "oui" : etat === "absent" ? "non" : "sans";
}

const MOTS: Record<UserRole, Record<Resultat, string>> = {
  Etudiant: { oui: "présent", non: "absent", sans: "sans appel" },
  Delegue: { oui: "tenue", non: "non tenue", sans: "sans appel" },
  Enseignant: { oui: "tenue", non: "non tenue", sans: "sans appel" },
  Admin: { oui: "tenue", non: "non tenue", sans: "sans appel" },
};

/** « 4 séances, présent à 3. » — la journée en une phrase, telle qu'on la dirait. */
function titre(n: number, oui: number, role: UserRole): string {
  if (n === 1) return "Une séance aujourd'hui.";
  const compte = oui === n ? "toutes" : oui === 0 ? "aucune" : String(oui);
  if (role === "Etudiant") return `${n} séances, présent à ${compte}.`;
  const accord = oui === n || oui > 1 ? "tenues" : "tenue";
  return `${n} séances, ${compte} ${accord}.`;
}

/**
 * Heures rondes qui encadrent la journée, espacées selon sa longueur, et
 * posées sur des multiples du pas (8 h, 10 h, 12 h… ou 0 h, 4 h, 8 h…) :
 * les repères tombent juste, sans deux étiquettes qui se chevauchent.
 */
function graduations(debut: number, fin: number): number[] {
  const portee = Math.ceil(fin / 60) - Math.floor(debut / 60);
  const pas = portee <= 6 ? 1 : portee <= 12 ? 2 : portee <= 18 ? 3 : 4;
  const premiere = Math.floor(debut / 60 / pas) * pas;
  const derniere = Math.ceil(fin / 60 / pas) * pas;
  const heures: number[] = [];
  for (let h = premiere; h <= derniere; h += pas) heures.push(h);
  return heures;
}

export function FinDeJournee({ seances, role }: { seances: Seance[]; role: UserRole }) {
  const ordonnees = [...seances].sort((a, b) => minutes(a.heure_debut) - minutes(b.heure_debut));
  const resultats = ordonnees.map((s) => resultat(s, role));
  const oui = resultats.filter((r) => r === "oui").length;

  const heures = graduations(
    Math.min(...ordonnees.map((s) => minutes(s.heure_debut))),
    Math.max(...ordonnees.map((s) => minutes(s.heure_fin))),
  );
  const origine = heures[0] * 60;
  const etendue = heures[heures.length - 1] * 60 - origine;
  const position = (m: number) => ((m - origine) / etendue) * 100;

  return (
    <section className="fin-journee" aria-label="Bilan de la journée">
      <h3>{titre(ordonnees.length, oui, role)}</h3>

      <div className="fin-journee-ligne" aria-hidden>
        <div className="fin-journee-piste">
          {ordonnees.map((s, i) => (
            <motion.span
              key={s.id}
              className={`fin-journee-seance fin-journee-${resultats[i]}`}
              style={{
                left: `${position(minutes(s.heure_debut))}%`,
                width: `${Math.max(1.5, position(minutes(s.heure_fin)) - position(minutes(s.heure_debut)))}%`,
              }}
              initial={{ scaleX: 0 }}
              animate={{ scaleX: 1 }}
              transition={{ duration: 0.55, delay: 0.12 + i * 0.07, ease: [0.22, 1, 0.36, 1] }}
            />
          ))}
        </div>
        <div className="fin-journee-heures">
          {heures.map((h, i) => (
            <span
              key={h}
              style={{ left: `${position(h * 60)}%` }}
              data-bord={i === 0 ? "gauche" : i === heures.length - 1 ? "droite" : undefined}
            >
              {h} h
            </span>
          ))}
        </div>
      </div>

      <ul className="fin-journee-liste">
        {ordonnees.map((s, i) => (
          <li key={s.id}>
            <time dateTime={s.heure_debut}>{s.heure_debut.slice(0, 5)}</time>
            <span className="fin-journee-nom">{s.matiere ?? "Séance de cours"}</span>
            <span className={`fin-journee-mot fin-journee-${resultats[i]}`}>{MOTS[role][resultats[i]]}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
