"use client";

import { motion } from "motion/react";

// Étoiles éparses en fond (bien moins denses que SpaceEmptyState : ici
// l'orbite est la vedette). Distribution par angle d'or, déterministe.
const STARS = Array.from({ length: 14 }, (_, i) => {
  const seed = i * 137.508;
  return {
    left: (seed * 4.1) % 100,
    top: (seed * 6.7) % 92,
    size: i % 4 === 0 ? 2.4 : 1.3,
    delay: (i % 5) * 0.5,
    duration: 2.4 + (i % 4) * 0.6,
  };
});

const RINGS = [
  { diameter: 128, duration: 11, reverse: false, dotSize: 6, opacity: 0.35 },
  { diameter: 176, duration: 17, reverse: true, dotSize: 5, opacity: 0.22 },
  { diameter: 216, duration: 23, reverse: false, dotSize: 4, opacity: 0.14 },
];

const COMETS = [
  { top: "18%", left: "4%", duration: 4.5, delay: 0.6, repeatDelay: 5.5, dx: 150, dy: 70 },
  { top: "62%", left: "62%", duration: 4, delay: 3.2, repeatDelay: 6, dx: 130, dy: 60 },
];

export function OrbitHero({
  heightClass = "h-56",
  roundedClass = "rounded-3xl",
  className,
}: {
  heightClass?: string;
  roundedClass?: string;
  className?: string;
}) {
  return (
    <div
      className={`relative flex ${heightClass} items-center justify-center overflow-hidden ${roundedClass} ${className ?? ""}`}
    >
      <div
        aria-hidden
        className="absolute inset-0"
        style={{ background: "linear-gradient(175deg, #0c0a26 0%, #181246 46%, #2f2394 100%)" }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 bottom-0 h-28"
        style={{
          background:
            "radial-gradient(60% 100% at 50% 100%, rgba(129,111,255,.4) 0%, transparent 70%)",
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{ boxShadow: "inset 0 0 60px 10px rgba(5,4,20,.6)" }}
      />

      {STARS.map((s, i) => (
        <motion.span
          key={i}
          aria-hidden
          className="absolute rounded-full bg-white"
          style={{ left: `${s.left}%`, top: `${s.top}%`, width: s.size, height: s.size }}
          animate={{ opacity: [0.1, 0.85, 0.1] }}
          transition={{ duration: s.duration, delay: s.delay, repeat: Infinity, ease: "easeInOut" }}
        />
      ))}

      {COMETS.map((c, i) => (
        <motion.span
          key={i}
          aria-hidden
          className="absolute h-px w-16 rounded-full"
          style={{
            top: c.top,
            left: c.left,
            background: "linear-gradient(90deg, transparent, white)",
            rotate: "-28deg",
          }}
          animate={{ x: [0, c.dx], y: [0, c.dy], opacity: [0, 1, 1, 0] }}
          transition={{
            duration: c.duration,
            delay: c.delay,
            repeat: Infinity,
            repeatDelay: c.repeatDelay,
            times: [0, 0.18, 0.75, 1],
            ease: "easeOut",
          }}
        />
      ))}

      {/* Système orbital : anneaux statiques + satellites qui tournent */}
      <div className="relative flex items-center justify-center">
        {RINGS.map((r, i) => (
          <div
            key={i}
            className="absolute rounded-full border"
            style={{
              width: r.diameter,
              height: r.diameter,
              borderColor: `rgba(255,255,255,${r.opacity})`,
            }}
          >
            <motion.div
              className="absolute inset-0"
              animate={{ rotate: r.reverse ? -360 : 360 }}
              transition={{ duration: r.duration, repeat: Infinity, ease: "linear" }}
            >
              <span
                className="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"
                style={{
                  width: r.dotSize,
                  height: r.dotSize,
                  boxShadow: "0 0 10px 2px rgba(196,181,253,.85)",
                }}
              />
            </motion.div>
          </div>
        ))}

        {/* Halo derrière le cœur */}
        <motion.div
          aria-hidden
          className="absolute h-32 w-32 rounded-full blur-2xl"
          style={{
            background: "radial-gradient(circle, rgba(140,124,255,.65) 0%, transparent 72%)",
          }}
          animate={{ opacity: [0.55, 0.9, 0.55], scale: [1, 1.1, 1] }}
          transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}
        />

        {/* Cœur lumineux */}
        <motion.div
          className="relative h-[72px] w-[72px] rounded-full"
          style={{
            background:
              "radial-gradient(circle at 34% 28%, #e9e6ff 0%, #7c6cf5 42%, #4630c9 78%, #2c2073 100%)",
            boxShadow: "0 0 0 1px rgba(255,255,255,.12), 0 14px 32px -8px rgba(30,20,90,.7)",
          }}
          animate={{ scale: [1, 1.045, 1] }}
          transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}
        >
          <div
            className="absolute left-[22%] top-[18%] h-[30%] w-[30%] rounded-full blur-[2px]"
            style={{ background: "rgba(255,255,255,.55)" }}
          />
        </motion.div>
      </div>
    </div>
  );
}
