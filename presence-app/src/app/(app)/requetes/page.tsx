"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";
import { motion, type Variants } from "motion/react";
import { AlertCircle, Paperclip } from "lucide-react";
import { SpaceEmptyState } from "@/components/space-empty-state";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { useMyRequetes, useSubmitRequete } from "@/hooks/use-requetes";
import { ApiError } from "@/lib/api-client";
import type { RequestStatus } from "@/types/api";
import { cn } from "@/lib/utils";

const STATUS_LABELS: Record<RequestStatus, { label: string; dot: string; className: string }> = {
  en_attente: { label: "En attente", dot: "bg-amber-500", className: "bg-warning/20 text-warning-foreground" },
  acceptee: { label: "Acceptée", dot: "bg-emerald-500", className: "bg-success/15 text-success" },
  rejetee: { label: "Rejetée", dot: "bg-rose-500", className: "bg-destructive/10 text-destructive" },
};

const listVariants: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.06, delayChildren: 0.1 } },
};
const itemVariants: Variants = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: [0.22, 1, 0.36, 1] } },
};

export default function RequetesPage() {
  return (
    <Suspense fallback={null}>
      <RequetesContent />
    </Suspense>
  );
}

function RequetesContent() {
  const searchParams = useSearchParams();
  const preselectedSeanceId = searchParams.get("seance_id") ?? "";

  const { data: requetes, isLoading } = useMyRequetes();
  const submit = useSubmitRequete();

  const [seanceId, setSeanceId] = useState(preselectedSeanceId);
  const [description, setDescription] = useState("");
  const [file, setFile] = useState<File | null>(null);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!file) return;

    const form = new FormData();
    form.append("seance_id", seanceId);
    form.append("description", description);
    form.append("preuve", file);

    submit.mutate(form, {
      onSuccess: () => {
        setDescription("");
        setFile(null);
      },
    });
  }

  const errorMessage = submit.error instanceof ApiError ? submit.error.message : null;

  return (
    <div className="flex flex-col gap-6">
      <motion.h1
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: "easeOut" }}
        className="font-display text-[26px] font-bold tracking-tight text-ink-900"
      >
        Mes requêtes
      </motion.h1>

      <motion.form
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, delay: 0.05, ease: [0.22, 1, 0.36, 1] }}
        onSubmit={handleSubmit}
        className="surface-card flex flex-col gap-3 rounded-2xl border border-line p-4"
      >
        <p className="text-sm font-semibold text-ink-900">Contester une séance</p>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="seance_id">N° de séance</Label>
          <Input
            id="seance_id"
            type="number"
            required
            value={seanceId}
            onChange={(e) => setSeanceId(e.target.value)}
            className="h-11 rounded-xl"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="description">Description</Label>
          <textarea
            id="description"
            required
            rows={3}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            className="rounded-xl border border-input bg-transparent px-3 py-2.5 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="preuve">Preuve (image ou PDF, 5 Mo max)</Label>
          <label
            htmlFor="preuve"
            className="flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-dashed border-input px-3 text-sm text-ink-500 transition-colors hover:border-primary/50 hover:text-ink-900"
          >
            <Paperclip className="h-4 w-4" />
            {file ? file.name : "Choisir un fichier"}
          </label>
          <input
            id="preuve"
            type="file"
            required
            accept="image/*,.pdf"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="hidden"
          />
        </div>
        {errorMessage && (
          <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}
        <motion.div whileTap={{ scale: 0.97 }} transition={{ type: "spring", stiffness: 500, damping: 22 }}>
          <Button type="submit" disabled={submit.isPending} className="h-11 w-full rounded-xl">
            {submit.isPending ? "Envoi…" : "Envoyer la requête"}
          </Button>
        </motion.div>
      </motion.form>

      {isLoading && <p className="text-sm text-ink-500">Chargement…</p>}

      <motion.div variants={listVariants} initial="hidden" animate="show" className="flex flex-col gap-2.5">
        {requetes?.map((r) => (
          <motion.div
            key={r.id}
            variants={itemVariants}
            className="surface-card flex flex-col gap-1.5 rounded-2xl border border-line p-4"
          >
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm font-semibold text-ink-900">
                {r.matiere} — {r.salle}
              </p>
              <span
                className={cn(
                  "flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium",
                  STATUS_LABELS[r.statut].className,
                )}
              >
                <span className={cn("h-1.5 w-1.5 rounded-full", STATUS_LABELS[r.statut].dot)} />
                {STATUS_LABELS[r.statut].label}
              </span>
            </div>
            <p className="text-xs text-ink-500">{r.description}</p>
            {r.commentaire_admin && (
              <p className="text-xs italic text-ink-300">Réponse admin : {r.commentaire_admin}</p>
            )}
          </motion.div>
        ))}
        {requetes?.length === 0 && <SpaceEmptyState title="Aucune requête pour l'instant" />}
      </motion.div>
    </div>
  );
}
