"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { motion } from "motion/react";
import { toast } from "sonner";
import { ShieldCheck, ShieldOff, Info, GraduationCap, UserCog, Presentation } from "lucide-react";
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

interface FaceAuthSetting {
  roles: string[];
  roles_reglables: string[];
}

const GRADES: Record<string, { icon: typeof GraduationCap; usage: string; risque: string }> = {
  Etudiant: {
    icon: GraduationCap,
    usage: "Pointe sa propre présence par GPS.",
    risque: "Un mot de passe partagé suffirait à pointer à la place d'un camarade absent.",
  },
  Delegue: {
    icon: UserCog,
    usage: "Étudiant promu : pointe pour lui-même et ouvre le pointage de sa salle.",
    risque: "Le compte qui ouvre le pointage de toute une salle ne serait plus protégé.",
  },
  Enseignant: {
    icon: Presentation,
    usage: "Déclare ses heures réelles, qui déterminent sa paie.",
    risque: "Des heures payées pourraient être déclarées par quelqu'un d'autre.",
  },
};

export default function ReconnaissanceFacialePage() {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ["parametres", "face-auth"],
    queryFn: () => apiFetch<FaceAuthSetting>("/api/parametres/face-auth"),
  });

  // Édition en cours, `null` tant que l'admin n'a rien touché : l'affichage
  // suit alors directement les données du serveur, sans effet de
  // synchronisation ni rendu en cascade.
  const [modifications, setModifications] = useState<string[] | null>(null);
  const [aDesactiver, setADesactiver] = useState<string | null>(null);
  const roles = modifications ?? data?.roles ?? [];

  const update = useMutation({
    mutationFn: (next: string[]) =>
      apiFetch<FaceAuthSetting>("/api/parametres/face-auth", {
        method: "PUT",
        body: JSON.stringify({ roles: next }),
      }),
    onSuccess: () => {
      setModifications(null);
      queryClient.invalidateQueries({ queryKey: ["parametres", "face-auth"] });
      toast.success("Réglage enregistré.");
    },
    onError: (e) =>
      toast.error(e instanceof ApiError ? e.message : "L'enregistrement a échoué."),
  });

  const modifie =
    modifications !== null &&
    data !== undefined &&
    [...modifications].sort().join(",") !== [...data.roles].sort().join(",");

  function basculer(role: string, actif: boolean) {
    // Activer renforce la sécurité : rien à confirmer. La désactiver la
    // retire, et c'est justement le geste dont on peut ne pas mesurer la
    // portée depuis cet écran — d'où la confirmation dans ce sens seulement.
    if (!actif) {
      setADesactiver(role);
      return;
    }

    setModifications([...roles, role]);
  }

  function confirmerDesactivation() {
    if (!aDesactiver) return;
    setModifications(roles.filter((r) => r !== aDesactiver));
    setADesactiver(null);
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Reconnaissance faciale
        </h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Les grades cochés doivent confirmer leur visage après le mot de passe. C&apos;est ce
          qui empêche qu&apos;un mot de passe partagé suffise à pointer, ou à déclarer des
          heures payées, à la place de quelqu&apos;un d&apos;autre.
        </p>
      </div>

      <div className="flex items-start gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10">
          <Info className="size-[18px] text-primary" />
        </div>
        <div className="text-[13px] leading-relaxed text-muted-foreground">
          <p className="font-medium text-foreground">Votre issue de secours</p>
          <p className="mt-1">
            Un compte qui n&apos;arrive pas à inscrire son visage (pas de caméra, mauvaise
            lumière) perd tout accès à l&apos;application. Décocher son grade ici le débloque
            immédiatement, sans redéploiement. L&apos;Admin n&apos;est jamais soumis au facial,
            précisément pour que cet écran reste toujours atteignable.
          </p>
        </div>
      </div>

      {isLoading && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {[...Array(3)].map((_, i) => (
            <Skeleton key={i} className="h-[116px] rounded-2xl" />
          ))}
        </div>
      )}

      <motion.div
        initial="hidden"
        animate="show"
        variants={{ show: { transition: { staggerChildren: 0.06 } } }}
        className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
      >
        {data?.roles_reglables.map((role) => (
          <CarteGrade
            key={role}
            role={role}
            actif={roles.includes(role)}
            onBasculer={(actif) => basculer(role, actif)}
          />
        ))}
      </motion.div>

      {data && (
        <div className="flex flex-wrap items-center gap-3 border-t border-border pt-4">
          <Button
            onClick={() => update.mutate(roles)}
            disabled={!modifie || update.isPending}
            className="gap-1.5"
          >
            {update.isPending ? "Enregistrement…" : "Enregistrer"}
          </Button>
          {modifie && (
            <span className="text-[13px] text-warning-foreground">
              Modifications non enregistrées — elles ne s&apos;appliquent qu&apos;après
              enregistrement.
            </span>
          )}
          {!modifie && (
            <span className="text-[13px] text-muted-foreground">
              {roles.length === 0
                ? "Aucun grade n'est soumis au facial."
                : `${roles.length} grade(s) soumis au facial.`}
            </span>
          )}
        </div>
      )}

      {aDesactiver && (
        <Dialog open onOpenChange={(o) => !o && setADesactiver(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Retirer le facial pour « {aDesactiver} » ?</DialogTitle>
              <DialogDescription>
                {GRADES[aDesactiver]?.risque ??
                  "Ce grade pourra se connecter avec le seul mot de passe."}{" "}
                Le réglage ne prendra effet qu&apos;après enregistrement, et reste réversible à
                tout moment.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setADesactiver(null)}>
                Annuler
              </Button>
              <Button variant="destructive" onClick={confirmerDesactivation}>
                Retirer le facial
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </div>
  );
}

function CarteGrade({
  role,
  actif,
  onBasculer,
}: {
  role: string;
  actif: boolean;
  onBasculer: (actif: boolean) => void;
}) {
  const grade = GRADES[role];
  const Icone = grade?.icon ?? GraduationCap;

  return (
    <motion.div
      variants={{
        hidden: { opacity: 0, y: 8 },
        show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] } },
      }}
    >
      {/* Association implicite (case imbriquée dans le label) : Base UI
          remplace l'id de la case par le sien, un htmlFor ne désignerait donc
          rien et le texte ne serait pas cliquable. */}
      <label
        className={cn(
          "flex cursor-pointer select-none items-start gap-3 rounded-2xl border p-4 shadow-xs transition-colors",
          actif ? "border-success/40 bg-success/5" : "border-border bg-card",
        )}
      >
        <Checkbox
          checked={actif}
          onCheckedChange={(checked) => onBasculer(checked === true)}
          className="mt-0.5"
        />
        <span className="min-w-0 flex-1">
          <span className="flex items-center gap-1.5">
            <Icone className="size-4 shrink-0 text-muted-foreground" />
            <span className="font-medium text-foreground">{role}</span>
            {actif ? (
              <ShieldCheck className="size-4 text-success" />
            ) : (
              <ShieldOff className="size-4 text-muted-foreground/50" />
            )}
          </span>
          <span className="mt-1 block text-xs leading-relaxed text-muted-foreground">
            {grade?.usage}
          </span>
        </span>
      </label>
    </motion.div>
  );
}
