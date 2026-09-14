"use client";

import { useId } from "react";

/**
 * Le sceau Ziris : trois anneaux entrelacés en nœud borroméen.
 *
 * Aucun anneau n'est accroché à un autre pris isolément — c'est l'entrelacement
 * des trois qui tient l'ensemble : l'étudiant, le délégué et l'enseignant.
 * Au centre, l'ouverture en triangle rappelle l'iris qui donne son nom à la marque.
 *
 * Géométrie (repère 32×32) : rayon 7,6 ; trait 2,9 ; centres à 3,4 du milieu,
 * répartis tous les 120°. Chaque anneau passe sous le suivant : le masque efface
 * le trait sur le passage de l'anneau qui le recouvre, jour de 1,9 de chaque côté.
 */
const RAYON = 7.6;
const TRAIT = 2.9;
const JOUR = 6.7; // trait du masque = TRAIT + 2 × 1,9

const ANNEAUX = [
  { cx: 16, cy: 12.6 },
  { cx: 18.945, cy: 17.7 },
  { cx: 13.055, cy: 17.7 },
] as const;

/** L'anneau qui passe au-dessus de celui d'indice i. */
const dessus = (i: number) => ANNEAUX[(i + 2) % 3];

export function ZirisMark({ size = 24 }: { size?: number }) {
  const brut = useId();
  const id = `ziris${brut.replace(/[^a-zA-Z0-9]/g, "")}`;

  return (
    <svg width={size} height={size} viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <defs>
        {ANNEAUX.map((_, i) => (
          <mask
            key={i}
            id={`${id}-${i}`}
            maskUnits="userSpaceOnUse"
            x="0"
            y="0"
            width="32"
            height="32"
          >
            <rect width="32" height="32" fill="#fff" />
            <circle
              cx={dessus(i).cx}
              cy={dessus(i).cy}
              r={RAYON}
              stroke="#000"
              strokeWidth={JOUR}
            />
          </mask>
        ))}
      </defs>
      <g stroke="currentColor" strokeWidth={TRAIT}>
        {ANNEAUX.map((anneau, i) => (
          <circle
            key={i}
            cx={anneau.cx}
            cy={anneau.cy}
            r={RAYON}
            mask={`url(#${id}-${i})`}
          />
        ))}
      </g>
    </svg>
  );
}

export function ZirisWordmark() {
  return <span className="ziris-wordmark">Z<span className="ziris-iris">i</span>ris</span>;
}
