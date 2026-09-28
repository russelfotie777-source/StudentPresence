"use client";

import Image from "next/image";
import { useEffect, useState } from "react";
import { motion, MotionConfig } from "motion/react";
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
const PROMPT_DELAY_MS = 15_000;
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
    <MotionConfig reducedMotion="user">
      <Dialog
        open={open}
        onOpenChange={(nextOpen) => {
          if (!nextOpen) dismiss();
          else setOpen(true);
        }}
      >
        <DialogContent
          data-install-prompt
          className="install-prompt-dialog gap-0 overflow-visible rounded-none border-0 bg-transparent p-0 text-[#17191d] shadow-none ring-0 data-closed:animate-none data-open:animate-none sm:max-w-[400px]"
          overlayClassName="bg-black/35 supports-backdrop-filter:backdrop-blur-none"
          showCloseButton
        >
          <motion.div
            initial={{ opacity: 0, y: 14, scale: 0.99 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ duration: 0.24, ease: [0.22, 1, 0.36, 1] }}
            className="max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-[10px] bg-white text-[#17191d] shadow-[0_20px_60px_rgba(15,23,42,0.20)] ring-1 ring-black/8"
          >
            <div className="px-5 pb-5 pt-5 sm:px-6 sm:pt-6">
              <div className="flex items-start gap-3.5 pr-7">
                <div className="relative h-11 w-11 shrink-0 overflow-hidden rounded-[8px] ring-1 ring-black/10">
                  <Image src="/ziris-192.png" alt="" fill sizes="44px" />
                </div>
                <DialogHeader className="gap-1.5 text-left">
                  <DialogTitle className="font-display text-xl font-semibold leading-snug text-[#17191d]">
                    {ios ? "Ajouter Ziris à votre iPhone" : "Installer Ziris"}
                  </DialogTitle>
                  <DialogDescription className="text-sm leading-relaxed text-[#697180]">
                    {ios
                      ? "Depuis Safari, ajoutez Ziris à votre écran d’accueil."
                      : "Accédez à Ziris directement depuis votre écran d’accueil."}
                  </DialogDescription>
                </DialogHeader>
              </div>

              {ios && (
                <ol className="mt-5 grid gap-2.5 border-t border-[#e7e9ed] pt-4">
                  {IOS_STEPS.map((step, index) => (
                    <li
                      key={step}
                      className="grid grid-cols-[1.15rem_1fr] gap-2.5 text-sm leading-relaxed text-[#555d6a]"
                    >
                      <span className="font-semibold text-[#17191d]">
                        {index + 1}.
                      </span>
                      <span>{step}</span>
                    </li>
                  ))}
                </ol>
              )}
            </div>

            <div className="border-t border-[#e7e9ed] px-5 py-4 sm:px-6">
              {ios ? (
                <motion.button
                  type="button"
                  onClick={dismiss}
                  whileTap={{ scale: 0.985 }}
                  className="inline-flex min-h-12 w-full items-center justify-center rounded-[8px] bg-[#17191d] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#272b32] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17191d]"
                >
                  J’ai compris
                </motion.button>
              ) : (
                <>
                  <motion.button
                    type="button"
                    onClick={install}
                    disabled={installing || !installEvent}
                    whileTap={{ scale: 0.985 }}
                    className="inline-flex min-h-12 w-full items-center justify-center rounded-[8px] bg-[#17191d] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#272b32] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17191d] disabled:cursor-wait disabled:opacity-60"
                  >
                    {installing ? "Ouverture…" : "Installer l’application"}
                  </motion.button>
                  <button
                    type="button"
                    onClick={dismiss}
                    className="mt-1 inline-flex min-h-10 w-full items-center justify-center rounded-[8px] px-4 text-sm font-medium text-[#666e7b] transition-colors hover:bg-[#f5f6f8] hover:text-[#17191d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17191d]"
                  >
                    Plus tard
                  </button>
                </>
              )}
            </div>
          </motion.div>
        </DialogContent>
      </Dialog>
    </MotionConfig>
  );
}
