"use client";

import { useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";
import { AnimatePresence, motion, MotionConfig } from "motion/react";
import { BottomNav } from "@/components/bottom-nav";
import { ZirisMark } from "@/components/ziris-brand";
import { useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";
import styles from "./workspace.module.css";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { data, isLoading, isError } = useMe();
  const user = data?.user;

  useEffect(() => {
    if (!getToken()) {
      router.replace("/login");
      return;
    }
    if (isError) {
      router.replace("/login");
      return;
    }
    if (!isLoading && data?.face_pending) {
      router.replace("/face");
      return;
    }
    if (
      !isLoading &&
      user &&
      user.role !== "Etudiant" &&
      user.validation_status !== "approved"
    ) {
      router.replace("/validation-en-attente");
    }
  }, [isLoading, isError, user, data, router]);

  // Écran de démarrage : il prend la suite de l'image figée que le système
  // affiche au lancement de l'application installée, d'où le vert de la tuile.
  if (isLoading || !user) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background">
        <span className="text-success">
          <ZirisMark size={54} anime />
        </span>
        <span className="text-ink-500 text-xs" role="status">
          Ouverture de votre espace&hellip;
        </span>
      </div>
    );
  }

  return (
    <MotionConfig reducedMotion="user">
      <div
        className={`app-shell ${pathname === "/dashboard" ? "is-dashboard" : pathname === "/historique" ? "is-history" : ""} ${pathname === "/dashboard" || pathname === "/historique" ? styles.workspace : ""}`}
      >
        <main className="app-main">
          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={pathname}
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
            >
              {children}
            </motion.div>
          </AnimatePresence>
        </main>
        <BottomNav role={user.effective_role} />
      </div>
    </MotionConfig>
  );
}
