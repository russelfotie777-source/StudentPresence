"use client";

import { useEffect, useRef } from "react";
import { animate, useMotionValue, useTransform } from "motion/react";

/**
 * Décompte animé (valeur numérique interpolée frame par frame, appliquée
 * directement au DOM via une souscription motion-value) — évite un re-render
 * React par frame, contrairement à un simple useState incrémenté dans une
 * boucle.
 */
export function AnimatedNumber({
  value,
  formatter = (n) => Math.round(n).toString(),
  duration = 1.1,
}: {
  value: number;
  formatter?: (n: number) => string;
  duration?: number;
}) {
  const motionValue = useMotionValue(0);
  const display = useTransform(motionValue, (v) => formatter(v));
  const ref = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const controls = animate(motionValue, value, { duration, ease: [0.22, 1, 0.36, 1] });
    return controls.stop;
  }, [value, duration, motionValue]);

  useEffect(() => {
    return display.on("change", (v) => {
      if (ref.current) ref.current.textContent = v;
    });
  }, [display]);

  return <span ref={ref}>{formatter(0)}</span>;
}
