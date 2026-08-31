"use client";

import { motion } from "motion/react";

const SIZE = 56;
const STROKE = 5;
const RADIUS = (SIZE - STROKE) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * Anneau d'assiduité animé (SVG + Framer Motion) — remplace le
 * conic-gradient statique du design canvas par un vrai tracé qui se dessine
 * à l'ouverture de l'écran.
 */
export function AttendanceRing({ percent }: { percent: number }) {
  const offset = CIRCUMFERENCE * (1 - percent / 100);

  return (
    <div className="relative flex h-14 w-14 shrink-0 items-center justify-center">
      <svg width={SIZE} height={SIZE} className="-rotate-90">
        <circle
          cx={SIZE / 2}
          cy={SIZE / 2}
          r={RADIUS}
          fill="none"
          stroke="var(--surface-2)"
          strokeWidth={STROKE}
        />
        <motion.circle
          cx={SIZE / 2}
          cy={SIZE / 2}
          r={RADIUS}
          fill="none"
          stroke="var(--indigo-600)"
          strokeWidth={STROKE}
          strokeLinecap="round"
          strokeDasharray={CIRCUMFERENCE}
          initial={{ strokeDashoffset: CIRCUMFERENCE }}
          animate={{ strokeDashoffset: offset }}
          transition={{ duration: 1, ease: [0.22, 1, 0.36, 1], delay: 0.15 }}
        />
      </svg>
      <div className="absolute flex h-[45px] w-[45px] items-center justify-center rounded-full bg-surface-1 shadow-sm">
        <span className="font-display text-[13px] font-extrabold text-ink-900">{percent}%</span>
      </div>
    </div>
  );
}
