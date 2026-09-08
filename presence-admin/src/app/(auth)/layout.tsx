"use client";

import { motion } from "motion/react";
import { CalendarRange, BadgeCheck, Wallet, ScanFace } from "lucide-react";
import { ThemeToggle } from "@/components/theme-toggle";

const POINTS = [
  { icon: CalendarRange, texte: "Emplois du temps générés et ajustés en quelques clics" },
  { icon: BadgeCheck, texte: "Validation des délégués et enseignants centralisée" },
  { icon: Wallet, texte: "Suivi des heures et de la paie, salle par salle" },
  { icon: ScanFace, texte: "Sécurité réglable : facial, sessions, permissions" },
];

/**
 * Coquille des écrans d'authentification : deux panneaux sur bureau, un seul
 * sur mobile. Remplace l'ancien centrage sur fond `bg-zinc-950` figé (qui
 * ignorait le système de thème) par une mise en scène qui installe
 * immédiatement le back-office comme un outil professionnel, pas un
 * formulaire administratif générique.
 */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen bg-background">
      <div className="relative hidden w-[46%] shrink-0 overflow-hidden bg-sidebar lg:flex lg:flex-col lg:justify-between lg:p-12 xl:w-[42%]">
        <PanneauDecor />

        <div className="relative z-10 flex items-center gap-2.5">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-md">
            P
          </div>
          <span className="font-display text-[15px] font-semibold text-sidebar-foreground">
            Présence
          </span>
        </div>

        <div className="relative z-10 max-w-md">
          <motion.h1
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
            className="font-display text-[34px] font-semibold leading-[1.15] tracking-tight text-sidebar-foreground"
          >
            Le centre de contrôle de votre campus.
          </motion.h1>
          <motion.p
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.08, ease: [0.22, 1, 0.36, 1] }}
            className="mt-3 text-[15px] leading-relaxed text-sidebar-foreground/60"
          >
            Salles, séances, présences et paie enseignante : administré depuis un
            seul endroit, pensé pour aller vite.
          </motion.p>

          <motion.ul
            initial="hidden"
            animate="show"
            variants={{ show: { transition: { staggerChildren: 0.08, delayChildren: 0.2 } } }}
            className="mt-8 flex flex-col gap-3.5"
          >
            {POINTS.map((point) => (
              <motion.li
                key={point.texte}
                variants={{
                  hidden: { opacity: 0, x: -8 },
                  show: { opacity: 1, x: 0, transition: { duration: 0.4, ease: [0.22, 1, 0.36, 1] } },
                }}
                className="flex items-center gap-3"
              >
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-accent">
                  <point.icon className="size-4 text-sidebar-accent-foreground" strokeWidth={2} />
                </div>
                <span className="text-[13.5px] text-sidebar-foreground/75">{point.texte}</span>
              </motion.li>
            ))}
          </motion.ul>
        </div>

        <p className="relative z-10 text-xs text-sidebar-foreground/40">
          Présence — Administration
        </p>
      </div>

      <div className="flex flex-1 flex-col">
        <div className="flex justify-end p-4 lg:p-6">
          <ThemeToggle />
        </div>
        <div className="flex flex-1 items-center justify-center px-4 pb-16">
          <div className="w-full max-w-[380px]">{children}</div>
        </div>
      </div>
    </div>
  );
}

/**
 * Trame de points + halos flottants, en pur CSS/SVG plutôt qu'en image :
 * aucun poids réseau, et la teinte suit automatiquement le thème clair/sombre
 * via les jetons de couleur existants.
 */
function PanneauDecor() {
  return (
    <div className="pointer-events-none absolute inset-0" aria-hidden>
      <div
        className="absolute inset-0 opacity-[0.4]"
        style={{
          backgroundImage:
            "radial-gradient(circle, color-mix(in oklch, var(--sidebar-foreground), transparent 85%) 1px, transparent 1px)",
          backgroundSize: "28px 28px",
        }}
      />
      <div
        className="absolute inset-0"
        style={{
          background:
            "radial-gradient(ellipse 60% 50% at 30% 0%, color-mix(in oklch, var(--sidebar), transparent 0%), var(--sidebar) 70%)",
        }}
      />
      <motion.div
        className="absolute -top-24 left-1/4 size-[420px] rounded-full blur-[100px]"
        style={{ background: "color-mix(in oklch, var(--primary), transparent 78%)" }}
        animate={{ y: [0, 24, 0], opacity: [0.7, 1, 0.7] }}
        transition={{ duration: 9, repeat: Infinity, ease: "easeInOut" }}
      />
      <motion.div
        className="absolute bottom-0 right-0 size-[320px] rounded-full blur-[90px]"
        style={{ background: "color-mix(in oklch, var(--accent), transparent 75%)" }}
        animate={{ y: [0, -20, 0], opacity: [0.5, 0.85, 0.5] }}
        transition={{ duration: 11, repeat: Infinity, ease: "easeInOut", delay: 1 }}
      />
    </div>
  );
}
