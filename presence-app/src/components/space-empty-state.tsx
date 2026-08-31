"use client";

import { motion } from "motion/react";

// Distribution des étoiles via l'angle d'or plutôt que Math.random() : motif
// organique mais déterministe (pas de désaccord SSR/CSR à l'hydratation).
const STARS = Array.from({ length: 28 }, (_, i) => {
  const seed = i * 137.508;
  return {
    left: (seed * 3.7) % 100,
    top: (seed * 5.3) % 88,
    size: i % 4 === 0 ? 3 : i % 3 === 0 ? 2 : 1.4,
    delay: (i % 6) * 0.35,
    duration: 2.2 + (i % 5) * 0.5,
  };
});

const BLOBS = [
  {
    id: "pod",
    color: "#4f46e5",
    highlight: "#a5b4fc",
    top: "14%",
    left: "12%",
    size: 72,
    floatY: 12,
    duration: 5.5,
    delay: 0,
    rotate: 6,
    shape: "42% 58% 55% 45% / 45% 42% 58% 55%",
  },
  {
    id: "head",
    color: "#e14a63",
    highlight: "#ffb8c6",
    top: "8%",
    left: "64%",
    size: 58,
    floatY: 10,
    duration: 4.6,
    delay: 0.4,
    rotate: -8,
    shape: "55% 45% 48% 52% / 52% 48% 55% 45%",
  },
  {
    id: "drop",
    color: "#f2994a",
    highlight: "#ffe1ad",
    top: "50%",
    left: "42%",
    size: 46,
    floatY: 14,
    duration: 6.2,
    delay: 0.8,
    rotate: 5,
    shape: "60% 40% 45% 55% / 50% 55% 45% 50%",
  },
];

export function SpaceEmptyState({
  title,
  subtitle,
  className,
}: {
  title: string;
  subtitle?: string;
  className?: string;
}) {
  return (
    <div
      className={`relative flex h-56 flex-col items-center justify-end overflow-hidden rounded-3xl ${className ?? ""}`}
    >
      <div
        aria-hidden
        className="absolute inset-0"
        style={{ background: "linear-gradient(175deg, #120e34 0%, #201958 48%, #3a2ba8 100%)" }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 bottom-0 h-36"
        style={{
          background:
            "radial-gradient(60% 100% at 50% 100%, rgba(168,142,255,.55) 0%, transparent 70%)",
        }}
      />

      {STARS.map((s, i) => (
        <motion.span
          key={i}
          aria-hidden
          className="absolute rounded-full bg-white"
          style={{ left: `${s.left}%`, top: `${s.top}%`, width: s.size, height: s.size }}
          animate={{ opacity: [0.15, 1, 0.15] }}
          transition={{ duration: s.duration, delay: s.delay, repeat: Infinity, ease: "easeInOut" }}
        />
      ))}

      {BLOBS.map((b) => (
        <motion.div
          key={b.id}
          aria-hidden
          className="absolute"
          style={{ top: b.top, left: b.left, width: b.size, height: b.size }}
          animate={{ y: [0, -b.floatY, 0], rotate: [0, b.rotate, 0] }}
          transition={{ duration: b.duration, delay: b.delay, repeat: Infinity, ease: "easeInOut" }}
        >
          <div
            className="relative h-full w-full shadow-[0_10px_28px_-6px_rgba(0,0,0,.5)]"
            style={{
              borderRadius: b.shape,
              background: `radial-gradient(circle at 32% 28%, ${b.highlight}, ${b.color})`,
            }}
          >
            <span className="absolute left-[36%] top-[40%] h-[8%] w-[8%] rounded-full bg-[#1a1330]" />
            <span className="absolute left-[58%] top-[40%] h-[8%] w-[8%] rounded-full bg-[#1a1330]" />
          </div>
        </motion.div>
      ))}

      <div className="relative z-10 flex flex-col items-center gap-1 pb-6 text-center">
        <p className="font-display text-[15px] font-bold text-white">{title}</p>
        {subtitle && <p className="text-xs text-white/70">{subtitle}</p>}
      </div>
    </div>
  );
}
