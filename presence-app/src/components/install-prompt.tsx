"use client";

import Image from "next/image";
import { useEffect, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

type InstallChoice = { outcome: "accepted" | "dismissed"; platform: string };

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<InstallChoice>;
}

interface NavigatorWithStandalone extends Navigator {
  standalone?: boolean;
}

const DISMISSED_AT_KEY = "ziris-install-dismissed-at";
const PROMPT_DELAY_MS = 2 * 60 * 1000;
const DISMISSAL_WINDOW_MS = 30 * 24 * 60 * 60 * 1000;

const IOS_STEPS = [
  "Ouvrez cette page dans Safari.",
  "Touchez Partager, puis « Sur l’écran d’accueil ».",
  "Activez « Ouvrir comme app », puis touchez Ajouter.",
];

function isInstalled() {
  return (
    window.matchMedia("(display-mode: standalone)").matches ||
    (navigator as NavigatorWithStandalone).standalone === true
  );
}

function isAppleMobile() {
  return (
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1)
  );
}

function dismissedRecently() {
  try {
    const dismissedAt = Number(window.localStorage.getItem(DISMISSED_AT_KEY));
    return (
      Number.isFinite(dismissedAt) &&
      Date.now() - dismissedAt < DISMISSAL_WINDOW_MS
    );
  } catch {
    return false;
  }
}

function rememberDismissal() {
  try {
    window.localStorage.setItem(DISMISSED_AT_KEY, String(Date.now()));
  } catch {
    // L'installation reste utilisable quand le stockage est indisponible.
  }
}

function clearDismissal() {
  try {
    window.localStorage.removeItem(DISMISSED_AT_KEY);
  } catch {
    // Le stockage peut être bloqué en navigation privée.
  }
}

export function InstallPrompt() {
  const [open, setOpen] = useState(false);
  const [ios] = useState(
    () => typeof navigator !== "undefined" && isAppleMobile(),
  );
  const [installEvent, setInstallEvent] =
    useState<BeforeInstallPromptEvent | null>(null);
  const [installing, setInstalling] = useState(false);

  useEffect(() => {
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker
        .register("/sw.js", { scope: "/" })
        .catch(() => undefined);
    }

    if (isInstalled() || dismissedRecently()) return;

    const showAfter = Date.now() + PROMPT_DELAY_MS;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let waitingForVisibility = false;

    const reveal = () => {
      if (document.visibilityState === "visible") {
        setOpen(true);
      } else {
        waitingForVisibility = true;
      }
    };

    const schedule = () => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(reveal, Math.max(0, showAfter - Date.now()));
    };

    if (ios) schedule();

    const handleInstallPrompt = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
      schedule();
    };

    const handleInstalled = () => {
      if (timer) clearTimeout(timer);
      waitingForVisibility = false;
      setOpen(false);
      setInstallEvent(null);
      clearDismissal();
    };

    const handleVisibilityChange = () => {
      if (waitingForVisibility && document.visibilityState === "visible") {
        waitingForVisibility = false;
        setOpen(true);
      }
    };

    window.addEventListener("beforeinstallprompt", handleInstallPrompt);
    window.addEventListener("appinstalled", handleInstalled);
    document.addEventListener("visibilitychange", handleVisibilityChange);

    return () => {
      if (timer) clearTimeout(timer);
      window.removeEventListener("beforeinstallprompt", handleInstallPrompt);
      window.removeEventListener("appinstalled", handleInstalled);
      document.removeEventListener("visibilitychange", handleVisibilityChange);
    };
  }, [ios]);

  function dismiss() {
    rememberDismissal();
    setOpen(false);
  }

  async function install() {
    if (!installEvent) return;
    setInstalling(true);

    try {
      await installEvent.prompt();
      const choice = await installEvent.userChoice;
      setInstallEvent(null);

      if (choice.outcome === "accepted") {
        setOpen(false);
        clearDismissal();
      } else {
        dismiss();
      }
    } finally {
      setInstalling(false);
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) dismiss();
        else setOpen(true);
      }}
    >
      <DialogContent
        className="max-h-[calc(100dvh-1rem)] gap-0 overflow-y-auto rounded-[8px] border-0 bg-white p-0 text-[#111827] shadow-[0_24px_80px_rgba(4,12,24,0.28)] ring-1 ring-black/10 sm:max-w-[420px]"
        showCloseButton
      >
        <div>
          <div className="px-6 pb-6 pt-7 sm:px-7">
            <div className="flex items-start gap-4 pr-7">
              <div className="relative h-12 w-12 shrink-0 overflow-hidden rounded-[8px] ring-1 ring-black/10">
                <Image src="/ziris-192.png" alt="" fill sizes="48px" />
              </div>
              <DialogHeader className="gap-2 text-left">
                <DialogTitle className="font-display text-xl font-semibold leading-snug text-[#111827]">
                  {ios ? "Ajouter Ziris à votre iPhone" : "Installer Ziris ?"}
                </DialogTitle>
                <DialogDescription className="text-sm leading-relaxed text-[#5f6877]">
                  {ios
                    ? "L’ajout à l’écran d’accueil se fait depuis Safari."
                    : "Ajoutez Ziris à votre écran d’accueil pour la retrouver plus facilement."}
                </DialogDescription>
              </DialogHeader>
            </div>

            {ios && (
              <ol className="mt-6 grid gap-3 border-t border-black/8 pt-5">
                {IOS_STEPS.map((step, index) => (
                  <li
                    key={step}
                    className="grid grid-cols-[1.25rem_1fr] gap-3 text-sm leading-relaxed text-[#4f5968]"
                  >
                    <span className="font-semibold text-[#087953]">
                      {index + 1}.
                    </span>
                    <span>{step}</span>
                  </li>
                ))}
              </ol>
            )}
          </div>

          <div className="flex flex-col gap-2 border-t border-black/8 bg-[#f7f9fb] px-6 py-4 sm:flex-row-reverse sm:px-7">
            {ios ? (
              <button
                type="button"
                onClick={dismiss}
                className="inline-flex h-11 flex-1 items-center justify-center rounded-[6px] bg-[#087953] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#066747] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#087953]"
              >
                D’accord
              </button>
            ) : (
              <button
                type="button"
                onClick={install}
                disabled={installing || !installEvent}
                className="inline-flex h-11 flex-1 items-center justify-center rounded-[6px] bg-[#087953] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#066747] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#087953] disabled:cursor-wait disabled:opacity-60"
              >
                {installing ? "Ouverture…" : "Installer"}
              </button>
            )}
            {!ios && (
              <button
                type="button"
                onClick={dismiss}
                className="inline-flex h-11 items-center justify-center rounded-[6px] px-4 text-sm font-medium text-[#5f6877] transition-colors hover:bg-black/5 hover:text-[#111827] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#087953]"
              >
                Pas maintenant
              </button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
