"use client";

import { useSyncExternalStore } from "react";
import { useTheme } from "next-themes";
import { Moon, Sun } from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

const noopSubscribe = () => () => {};

/**
 * next-themes ne connaît le thème résolu qu'après hydratation ; ce hook
 * distingue le premier rendu serveur du rendu client sans setState dans un
 * effet (cf. react-hooks/set-state-in-effect).
 */
function useMounted() {
  return useSyncExternalStore(
    noopSubscribe,
    () => true,
    () => false
  );
}

export function ThemeToggle({ className }: { className?: string }) {
  const { resolvedTheme, setTheme } = useTheme();
  const mounted = useMounted();

  const isDark = mounted && resolvedTheme === "dark";

  return (
    <button
      type="button"
      aria-label="Changer de thème"
      onClick={() => setTheme(isDark ? "light" : "dark")}
      className={`relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-card shadow-sm ${className ?? ""}`}
    >
      <AnimatePresence mode="wait" initial={false}>
        {mounted && (
          <motion.span
            key={isDark ? "moon" : "sun"}
            initial={{ opacity: 0, rotate: -60, scale: 0.5 }}
            animate={{ opacity: 1, rotate: 0, scale: 1 }}
            exit={{ opacity: 0, rotate: 60, scale: 0.5 }}
            transition={{ duration: 0.25, ease: "easeOut" }}
            className="flex items-center justify-center"
          >
            {isDark ? (
              <Moon className="h-[18px] w-[18px] text-indigo-400" />
            ) : (
              <Sun className="h-[18px] w-[18px] text-amber-500" />
            )}
          </motion.span>
        )}
      </AnimatePresence>
    </button>
  );
}
