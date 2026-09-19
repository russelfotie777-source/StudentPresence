"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Info, UserCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { apiFetch, ApiError } from "@/lib/api-client";
import { cn } from "@/lib/utils";

interface ReglagesPointage {
  delegue_confirme_enseignant: boolean;
}

/**
 * Règles de pointage réglables sans redéploiement. Une seule pour l'instant :
 * le délégué peut-il confirmer la présence de l'enseignant à sa place —
 * pour les enseignants qui n'ouvrent jamais l'application, dont les séances
 * resteraient sinon « non tenues » faute de leur réponse.
 */
export default function PointagePage() {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ["parametres", "pointage"],
    queryFn: () => apiFetch<ReglagesPointage>("/api/parametres/pointage"),
  });
  const [aActiver, setAActiver] = useState(false);

  const update = useMutation({
    mutationFn: (delegue_confirme_enseignant: boolean) =>
      apiFetch<ReglagesPointage>("/api/parametres/pointage", {
        method: "PUT",
        body: JSON.stringify({ delegue_confirme_enseignant }),
      }),
    onSuccess: (r) => {
      queryClient.setQueryData(["parametres", "pointage"], r);
      toast.success(
        r.delegue_confirme_enseignant
          ? "Les délégués peuvent désormais confirmer pour l'enseignant."
          : "Seul l'enseignant confirme désormais sa présence.",
      );
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "L'enregistrement a échoué."),
  });

  const actif = data?.delegue_confirme_enseignant ?? false;

  // Activer donne au délégué un pouvoir qui pèse sur la paie : on le dit
  // avant. Désactiver ne retire rien à personne d'autre qu'au délégué.
  function basculer(prochain: boolean) {
    if (prochain) setAActiver(true);
    else update.mutate(false);
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Règles de pointage
        </h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Ce que délégués et enseignants peuvent déclarer sur une séance. Chaque règle
          s&apos;applique immédiatement, sans redéploiement, et reste réversible.
        </p>
      </div>

      <div className="flex items-start gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10">
          <Info className="size-[18px] text-primary" />
        </div>
        <div className="text-[13px] leading-relaxed text-muted-foreground">
          <p className="font-medium text-foreground">Comment une séance est comptée « tenue »</p>
          <p className="mt-1">
            Il faut deux réponses concordantes : le délégué marque l&apos;enseignant présent, et
            l&apos;enseignant confirme lui-même depuis son application. Un enseignant qui
            n&apos;utilise pas l&apos;application ne répond jamais — sa séance reste « non tenue »
            quoi qu&apos;ait constaté le délégué, et ses heures ne lui sont pas comptées.
          </p>
        </div>
      </div>

      {isLoading ? (
        <Skeleton className="h-[132px] rounded-2xl" />
      ) : (
        <label
          className={cn(
            "flex max-w-2xl cursor-pointer select-none items-start gap-3 rounded-2xl border p-4 shadow-xs transition-colors",
            actif ? "border-success/40 bg-success/5" : "border-border bg-card",
          )}
        >
          <Checkbox
            checked={actif}
            disabled={update.isPending}
            onCheckedChange={(v) => basculer(v === true)}
            className="mt-0.5"
          />
          <div className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex items-center gap-2 text-sm font-medium text-foreground">
              <UserCheck className="size-4 text-muted-foreground" />
              Le délégué peut confirmer la présence de l&apos;enseignant à sa place
            </span>
            <span className="text-[13px] leading-relaxed text-muted-foreground">
              Pendant la séance, le délégué dispose d&apos;un bouton « Confirmer pour
              l&apos;enseignant » : la séance est alors comptée tenue sans que l&apos;enseignant
              ait à ouvrir l&apos;application. La confirmation est tracée comme venant du
              délégué ; un enseignant qui répond lui-même garde toujours le dernier mot.
            </span>
            <span className={cn("text-xs font-medium", actif ? "text-success" : "text-muted-foreground")}>
              {actif ? "Activé pour tous les délégués." : "Désactivé : seul l'enseignant confirme."}
            </span>
          </div>
        </label>
      )}

      {aActiver && (
        <Dialog open onOpenChange={(o) => !o && setAActiver(false)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Laisser les délégués confirmer pour l&apos;enseignant ?</DialogTitle>
              <DialogDescription>
                Une séance confirmée par le délégué compte comme tenue et entre dans les heures
                payées de l&apos;enseignant, même s&apos;il n&apos;a rien déclaré. Chaque confirmation
                garde la trace du délégué qui l&apos;a donnée, et l&apos;enseignant peut toujours la
                corriger depuis son application. Réversible à tout moment.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setAActiver(false)}>
                Annuler
              </Button>
              <Button
                disabled={update.isPending}
                onClick={() => update.mutate(true, { onSettled: () => setAActiver(false) })}
              >
                Activer
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </div>
  );
}
