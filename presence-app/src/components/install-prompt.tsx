"use client";

import Image from "next/image";
import { useEffect, useState } from "react";
import { motion, MotionConfig } from "motion/react";
import {
  BellRing,
  Check,
  Clock3,
  Download,
  ExternalLink,
  MonitorSmartphone,
  Share,
  SquarePlus,
} from "lucide-react";
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
const SEVEN_DAYS = 7 * 24 * 60 * 60 * 1000;

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
  const dismissedAt = Number(window.localStorage.getItem(DISMISSED_AT_KEY));
  return Number.isFinite(dismissedAt) && Date.now() - dismissedAt < SEVEN_DAYS;
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
      navigator.serviceWorker.register("/sw.js", { scope: "/" }).catch(() => undefined);
    }

    if (isInstalled() || dismissedRecently()) return;

    let timer: ReturnType<typeof setTimeout> | undefined;
    if (ios) {
      timer = setTimeout(() => setOpen(true), 1400);
    }

    const handleInstallPrompt = (event: Event) => {
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
      timer = setTimeout(() => setOpen(true), 900);
    };

    const handleInstalled = () => {
      setOpen(false);
      setInstallEvent(null);
      window.localStorage.removeItem(DISMISSED_AT_KEY);
    };

    window.addEventListener("beforeinstallprompt", handleInstallPrompt);
    window.addEventListener("appinstalled", handleInstalled);

    return () => {
      if (timer) clearTimeout(timer);
      window.removeEventListener("beforeinstallprompt", handleInstallPrompt);
      window.removeEventListener("appinstalled", handleInstalled);
    };
  }, [ios]);

  function dismiss() {
    window.localStorage.setItem(DISMISSED_AT_KEY, String(Date.now()));
    setOpen(false);
  }

  async function install() {
    if (!installEvent) return;
    setInstalling(true);
    await installEvent.prompt();
    const choice = await installEvent.userChoice;
    setInstallEvent(null);
    setInstalling(false);

    if (choice.outcome === "accepted") {
      setOpen(false);
      window.localStorage.removeItem(DISMISSED_AT_KEY);
    } else {
      dismiss();
    }
  }

  const steps = [
    {
      icon: ExternalLink,
      title: "Ouvrez Ziris dans Safari",
      detail: "L’installation depuis l’écran d’accueil passe par Safari.",
    },
    {
      icon: Share,
      title: "Touchez Partager",
      detail: "Utilisez l’icône carrée avec la flèche vers le haut.",
    },
    {
      icon: SquarePlus,
      title: "Sur l’écran d’accueil",
      detail: "Activez « Ouvrir comme app », puis touchez Ajouter.",
    },
  ];

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
          className="max-h-[calc(100dvh-1rem)] overflow-y-auto rounded-[8px] border-0 bg-[#0f1828] p-0 text-white ring-1 ring-white/15 sm:max-w-[460px]"
          showCloseButton
        >
          <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
          >
            <div className="border-b border-white/10 px-6 pb-5 pt-6">
              <div className="flex items-start gap-4 pr-8">
                <motion.div
                  initial={{ scale: 0.72, rotate: -8 }}
                  animate={{ scale: 1, rotate: 0 }}
                  transition={{ type: "spring", stiffness: 310, damping: 20 }}
                  className="relative h-16 w-16 shrink-0 overflow-hidden rounded-[8px] ring-1 ring-white/15"
                >
                  <Image src="/ziris-192.png" alt="" fill sizes="64px" />
                </motion.div>
                <DialogHeader className="pt-1 text-left">
                  <p className="text-xs font-semibold text-[#55e7ad]">
                    Ziris sur votre écran d’accueil
                  </p>
                  <DialogTitle className="font-display text-2xl font-bold leading-tight text-white">
                    {ios ? "Installer sur cet iPhone" : "Installer Ziris"}
                  </DialogTitle>
                  <DialogDescription className="text-sm leading-relaxed text-white/65">
                    {ios
                      ? "Trois gestes suffisent pour ouvrir Ziris comme une vraie app."
                      : "Un accès direct, plein écran et prêt pour vos rappels de présence."}
                  </DialogDescription>
                </DialogHeader>
              </div>
            </div>

            {ios ? (
              <ol className="grid gap-0 px-6 py-3">
                {steps.map((step, index) => {
                  const Icon = step.icon;
                  return (
                    <motion.li
                      key={step.title}
                      initial={{ opacity: 0, x: -12 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ duration: 0.35, delay: 0.12 + index * 0.08 }}
                      className="flex gap-4 border-b border-white/8 py-4 last:border-0"
                    >
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white/8 text-[#55e7ad] ring-1 ring-white/10">
                        <Icon size={19} aria-hidden="true" />
                      </span>
                      <span className="min-w-0">
                        <strong className="block text-sm font-semibold text-white">
                          {index + 1}. {step.title}
                        </strong>
                        <span className="mt-1 block text-xs leading-relaxed text-white/55">
                          {step.detail}
                        </span>
                      </span>
                    </motion.li>
                  );
                })}
              </ol>
            ) : (
              <div className="grid grid-cols-3 border-b border-white/10 px-6 py-6 text-center">
                {[
                  { icon: MonitorSmartphone, label: "Accès direct" },
                  { icon: BellRing, label: "Rappels prêts" },
                  { icon: Check, label: "Plein écran" },
                ].map(({ icon: Icon, label }, index) => (
                  <motion.div
                    key={label}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.35, delay: 0.1 + index * 0.08 }}
                    className="flex min-w-0 flex-col items-center gap-2 px-1"
                  >
                    <Icon size={20} className="text-[#55e7ad]" aria-hidden="true" />
                    <span className="text-xs font-medium text-white/70">{label}</span>
                  </motion.div>
                ))}
              </div>
            )}

            <div className="flex flex-col gap-2 px-6 py-5 sm:flex-row-reverse">
              {ios ? (
                <button
                  type="button"
                  onClick={dismiss}
                  className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md bg-[#18d98b] px-5 text-sm font-semibold text-[#07180f] transition-colors hover:bg-[#55e7ad] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#55e7ad]"
                >
                  <Check size={18} aria-hidden="true" />
                  J’ai compris
                </button>
              ) : (
                <button
                  type="button"
                  onClick={install}
                  disabled={installing || !installEvent}
                  className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md bg-[#18d98b] px-5 text-sm font-semibold text-[#07180f] transition-colors hover:bg-[#55e7ad] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#55e7ad] disabled:cursor-wait disabled:opacity-60"
                >
                  <Download size={18} aria-hidden="true" />
                  {installing ? "Ouverture…" : "Installer Ziris"}
                </button>
              )}
              <button
                type="button"
                onClick={dismiss}
                className="inline-flex h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium text-white/65 transition-colors hover:bg-white/8 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
              >
                <Clock3 size={17} aria-hidden="true" />
                Plus tard
              </button>
            </div>
          </motion.div>
        </DialogContent>
      </Dialog>
    </MotionConfig>
  );
}
