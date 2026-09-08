"use client";

import { useTheme } from "next-themes";
import { Toaster, type ToasterProps } from "sonner";

export function AppToaster() {
  const { resolvedTheme } = useTheme();

  return (
    <Toaster
      theme={resolvedTheme as ToasterProps["theme"]}
      position="top-right"
      richColors
      closeButton
      toastOptions={{
        classNames: {
          toast: "rounded-xl! border-border! bg-card! text-foreground! shadow-lg!",
          description: "text-muted-foreground!",
        },
      }}
    />
  );
}
