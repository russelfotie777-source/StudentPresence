"use client";

import { useSyncExternalStore } from "react";
import { useTheme } from "next-themes";
import { Moon, Sun } from "lucide-react";
import { Button } from "@/components/ui/button";

/**
 * Bascule clair/sombre à deux états (pas de "système" ici) : un admin qui
 * choisit explicitement un thème pour son écran de travail ne veut
 * généralement pas qu'il change tout seul au coucher du soleil.
 */
export function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();
  // Le thème résolu dépend de prefers-color-scheme côté client : le rendre
  // avant l'hydratation créerait un flash entre le rendu serveur et client.
  // useSyncExternalStore rend `false` côté serveur et `true` côté client sans
  // passer par un setState dans un effet.
  const monte = useSyncExternalStore(
    () => () => {},
    () => true,
    () => false,
  );

  if (!monte) {
    return <div className="size-8" aria-hidden />;
  }

  const sombre = resolvedTheme === "dark";

  return (
    <Button
      variant="ghost"
      size="icon"
      onClick={() => setTheme(sombre ? "light" : "dark")}
      aria-label={sombre ? "Passer au thème clair" : "Passer au thème sombre"}
    >
      {sombre ? <Sun className="size-4" /> : <Moon className="size-4" />}
    </Button>
  );
}
