"use client";

import { useTheme } from "next-themes";
import { Toaster, type ToasterProps } from "sonner";

export function AppToaster() {
  const { resolvedTheme } = useTheme();

  return (
    <Toaster
      theme={resolvedTheme as ToasterProps["theme"]}
      position="top-center"
      richColors
      closeButton
      toastOptions={{
        classNames: {
          toast: "rounded-2xl! border-line! bg-card! text-ink-900! shadow-lg!",
          description: "text-ink-500!",
        },
      }}
    />
  );
}
