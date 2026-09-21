"use client";

import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { motion } from "motion/react";
import { Hand, MapPin } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { useFaireSigneDelegue } from "@/hooks/use-seances";
import type { Seance } from "@/types/api";

/**
 * L'heure est venue, l'étudiant appuie sur « Je suis présent(e) » — mais le
 * délégué n'a pas encore envoyé la position de la salle. On le lui dit
 * simplement, on lui laisse faire signe au délégué, et on guette la
 * position : dès qu'elle arrive, le pointage s'ouvre à sa place.
 */
export function AttentePosition({
  seance,
  open,
  onOpenChange,
}: {
  seance: Seance;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const queryClient = useQueryClient();
  const signe = useFaireSigneDelegue(seance.id);

  // Tant que la fenêtre est ouverte, on regarde souvent si la position est
  // arrivée : le tableau de bord se rafraîchit, et remplace ce dialogue par
  // le pointage sans que l'étudiant ait rien à refaire.
  useEffect(() => {
    if (!open) return;
    const t = setInterval(() => queryClient.invalidateQueries({ queryKey: ["seances", "today"] }), 15_000);
    return () => clearInterval(t);
  }, [open, queryClient]);

  const prenoms = signe.data?.delegues ?? [];
  const qui = prenoms.length ? liste(prenoms) : "le délégué";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="rounded-lg">
        <DialogHeader>
          <DialogTitle className="font-display text-lg font-bold">Pas encore.</DialogTitle>
          <DialogDescription>
            {seance.matiere} — {seance.salle}, {seance.heure_debut.slice(0, 5)}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col items-center gap-3 py-3 text-center">
          <Balise />
          <h2 className="font-display text-lg font-bold text-ink-900">
            Le délégué n&apos;a pas encore envoyé la position.
          </h2>
          <p className="max-w-[290px] text-sm leading-relaxed text-ink-500">
            Le pointage s&apos;ouvre quand il envoie la position de la salle. Dès qu&apos;elle
            arrive, vous pourrez pointer d&apos;ici — sans rien fermer.
          </p>

          {signe.isSuccess ? (
            <p className="mt-1 rounded-xl bg-indigo-50 px-3.5 py-2.5 text-sm text-indigo-600" role="status">
              {signe.data.deja_prevenu
                ? `Quelqu'un a déjà fait signe à ${qui}.`
                : `C'est fait, ${qui} ${prenoms.length > 1 ? "sont" : "est"} au courant.`}
            </p>
          ) : (
            <p className="mt-1 inline-flex items-center gap-2 text-xs text-ink-300" role="status">
              <span className="relative flex size-2">
                <span className="animate-dc-pulse absolute inset-0 rounded-full bg-indigo-500" />
                <span className="relative size-2 rounded-full bg-indigo-500" />
              </span>
              On guette la position de {seance.salle}.
            </p>
          )}
        </div>

        <DialogFooter className="gap-2">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Plus tard
          </Button>
          <Button
            className="gap-2"
            disabled={signe.isPending || signe.isSuccess}
            onClick={() => signe.mutate()}
          >
            <Hand size={17} />
            {signe.isPending ? "Un instant…" : signe.isSuccess ? "Signe fait" : "Faire signe au délégué"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/**
 * La balise qui n'est pas encore posée : le repère au sol respire, et le
 * point flotte au-dessus en attendant de s'y poser.
 */
function Balise() {
  return (
    <div className="relative mb-1 flex h-[104px] w-[104px] items-end justify-center" aria-hidden>
      <span className="animate-dc-pulse absolute bottom-3 left-1/2 h-9 w-[76px] -translate-x-1/2 rounded-[50%] bg-indigo-100" />
      <span className="absolute bottom-3 left-1/2 h-9 w-[76px] -translate-x-1/2 rounded-[50%] border-2 border-dashed border-indigo-500/60" />
      <motion.div
        className="relative mb-8 flex h-[58px] w-[58px] items-center justify-center rounded-full bg-indigo-50 text-indigo-600"
        animate={{ y: [0, -8, 0] }}
        transition={{ duration: 2.4, ease: "easeInOut", repeat: Infinity }}
      >
        <MapPin className="h-7 w-7" strokeWidth={1.9} />
      </motion.div>
    </div>
  );
}

function liste(prenoms: string[]): string {
  if (prenoms.length <= 1) return prenoms.join("");
  return `${prenoms.slice(0, -1).join(", ")} et ${prenoms[prenoms.length - 1]}`;
}
