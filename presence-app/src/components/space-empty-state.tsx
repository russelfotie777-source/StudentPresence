"use client";

import { motion } from "motion/react";

// Distribution des étoiles via l'angle d'or plutôt que Math.random() : motif
// organique mais déterministe (pas de désaccord SSR/CSR à l'hydratation).
const STARS = Array.from({ length: 32 }, (_, i) => {
  const seed = i * 137.508;
  return {
    left: (seed * 3.7) % 100,
    top: (seed * 5.3) % 88,
    size: i % 5 === 0 ? 3.2 : i % 3 === 0 ? 2 : 1.2,
    delay: (i % 6) * 0.35,
    duration: 2.2 + (i % 5) * 0.5,
  };
});

const BLOBS = [
  {
    id: "pod",
    color: "#4f46e5",
    highlight: "#b4bcfd",
    shadow: "#241f6e",
    glow: "rgba(99,91,255,.45)",
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
    highlight: "#ffc3d0",
    shadow: "#7a2536",
    glow: "rgba(225,74,99,.4)",
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
    highlight: "#ffe6b8",
    shadow: "#8a4d16",
    glow: "rgba(242,153,74,.4)",
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
  heightClass = "h-56",
  roundedClass = "rounded-3xl",
}: {
  title?: string;
  subtitle?: string;
  className?: string;
  heightClass?: string;
  roundedClass?: string;
}) {
  return (
    <div
      className={`relative flex ${heightClass} flex-col items-center justify-end overflow-hidden ${roundedClass} ${className ?? ""}`}
    >
      <div
        aria-hidden
        className="absolute inset-0"
        style={{ background: "linear-gradient(175deg, #100c30 0%, #1c1550 46%, #3a2ba8 100%)" }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 bottom-0 h-36"
        style={{
          background:
            "radial-gradient(60% 100% at 50% 100%, rgba(168,142,255,.5) 0%, transparent 70%)",
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{ boxShadow: "inset 0 0 60px 10px rgba(6,4,24,.55)" }}
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
          {/* Halo lumineux derrière la forme, comme un éclairage ambiant */}
          <div
            className="absolute rounded-full blur-xl"
            style={{
              inset: "-35%",
              background: `radial-gradient(circle, ${b.glow} 0%, transparent 70%)`,
            }}
          />
          <div
            className="relative h-full w-full"
            style={{
              borderRadius: b.shape,
              background: `radial-gradient(circle at 30% 26%, ${b.highlight} 0%, ${b.color} 55%, ${b.shadow} 100%)`,
              boxShadow: `inset -5px -7px 10px ${b.shadow}99, 0 12px 26px -6px rgba(0,0,0,.55)`,
            }}
          >
            <span className="absolute left-[35%] top-[39%] h-[9%] w-[9%] rounded-full bg-[#191029]">
              <span className="absolute left-[20%] top-[15%] h-[35%] w-[35%] rounded-full bg-white/80" />
            </span>
            <span className="absolute left-[57%] top-[39%] h-[9%] w-[9%] rounded-full bg-[#191029]">
              <span className="absolute left-[20%] top-[15%] h-[35%] w-[35%] rounded-full bg-white/80" />
            </span>
          </div>
        </motion.div>
      ))}

      {title && (
        <div className="relative z-10 flex flex-col items-center gap-1 pb-6 text-center">
          <p className="font-display text-[15px] font-bold text-white">{title}</p>
          {subtitle && <p className="text-xs text-white/70">{subtitle}</p>}
        </div>
      )}
    </div>
  );
}
