"use client";

import { useState } from "react";
import { motion, type Variants } from "motion/react";
import { AlertCircle, Search, UserPlus2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useActivePromotions, useCreatePromotion, useStudentSearch } from "@/hooks/use-promotions";
import { ApiError } from "@/lib/api-client";

const listVariants: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.06, delayChildren: 0.1 } },
};
const itemVariants: Variants = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: [0.22, 1, 0.36, 1] } },
};

export default function PromotionPage() {
  const [search, setSearch] = useState("");
  const [duree, setDuree] = useState(60);
  const { data: students } = useStudentSearch(search);
  const { data: actives } = useActivePromotions();
  const createPromotion = useCreatePromotion();

  const errorMessage =
    createPromotion.error instanceof ApiError
      ? (createPromotion.error.errors?.etudiant_id?.[0] ?? createPromotion.error.message)
      : null;

  return (
    <div className="flex flex-col gap-6">
      <motion.div
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: "easeOut" }}
      >
        <h1 className="font-display text-[26px] font-bold tracking-tight text-ink-900">
          Promotion temporaire
        </h1>
        <p className="mt-1 text-sm text-ink-500">
          Donnez temporairement les droits de délégué à un étudiant.
        </p>
      </motion.div>

      <motion.div
        initial={{ opacity: 0, y: 14 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, delay: 0.05, ease: [0.22, 1, 0.36, 1] }}
        className="flex gap-2"
      >
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-300" />
          <Input
            placeholder="Rechercher un étudiant…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="h-11 rounded-xl pl-10"
          />
        </div>
        <Input
          type="number"
          min={1}
          max={1440}
          value={duree}
          onChange={(e) => setDuree(Number(e.target.value))}
          className="h-11 w-20 rounded-xl text-center"
          title="Durée en minutes"
        />
      </motion.div>

      {errorMessage && (
        <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      <motion.div variants={listVariants} initial="hidden" animate="show" className="flex flex-col gap-2.5">
        {students?.map((s) => (
          <motion.div
            key={s.id}
            variants={itemVariants}
            className="flex items-center justify-between gap-3 rounded-2xl border border-line bg-card p-4"
          >
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-ink-900">{s.name}</p>
              <p className="truncate text-xs text-ink-500">
                {s.salle} · {s.filiere} · {s.niveau}
              </p>
            </div>
            {s.has_active_promotion ? (
              <span className="shrink-0 rounded-full bg-secondary px-2.5 py-1 text-[11px] font-medium text-secondary-foreground">
                Déjà promu
              </span>
            ) : (
              <motion.div whileTap={{ scale: 0.94 }} transition={{ type: "spring", stiffness: 500, damping: 22 }}>
                <Button
                  size="sm"
                  className="h-9 shrink-0 gap-1.5 rounded-lg"
                  disabled={createPromotion.isPending}
                  onClick={() => createPromotion.mutate({ etudiant_id: s.id, duree_minutes: duree })}
                >
                  <UserPlus2 className="h-3.5 w-3.5" />
                  Promouvoir
                </Button>
              </motion.div>
            )}
          </motion.div>
        ))}
      </motion.div>

      {actives && actives.length > 0 && (
        <div className="flex flex-col gap-2.5">
          <h2 className="text-sm font-semibold text-ink-900">Promotions actives</h2>
          {actives.map((p) => (
            <div
              key={p.id}
              className="flex items-center justify-between rounded-2xl border border-line bg-card p-4 text-sm"
            >
              <span className="font-medium text-ink-900">
                {p.etudiant.name} <span className="font-normal text-ink-500">— {p.etudiant.salle?.nom}</span>
              </span>
              <span className="text-xs text-ink-500">
                jusqu&apos;à{" "}
                {new Date(p.date_fin).toLocaleTimeString("fr-FR", {
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
