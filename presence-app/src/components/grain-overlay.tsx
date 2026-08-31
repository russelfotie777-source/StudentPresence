const GRAIN_SVG =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E";

/**
 * Texture grain procédurale (SVG feTurbulence, pas une image) posée en
 * overlay sur une surface en dégradé — casse l'effet "flat design"
 * générique. Repris du design canvas premium.
 */
export function GrainOverlay({ className }: { className?: string }) {
  return (
    <div
      aria-hidden
      className={`pointer-events-none absolute inset-0 opacity-50 mix-blend-overlay ${className ?? ""}`}
      style={{ backgroundImage: `url("${GRAIN_SVG}")` }}
    />
  );
}
