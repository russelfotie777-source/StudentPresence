"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ShieldCheck, Info } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { apiFetch } from "@/lib/api-client";

interface FaceAuthSetting {
  roles: string[];
  roles_reglables: string[];
}

const DESCRIPTIONS: Record<string, string> = {
  Etudiant: "Pointe sa propre présence par GPS.",
  Delegue: "Étudiant promu : pointe pour lui-même et ouvre le pointage de sa salle.",
  Enseignant: "Déclare ses heures réelles, qui déterminent sa paie.",
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
    },
  });

  const modifie =
    modifications !== null &&
    data !== undefined &&
    [...modifications].sort().join(",") !== [...data.roles].sort().join(",");

  function toggle(role: string, actif: boolean) {
    setModifications(actif ? [...roles, role] : roles.filter((r) => r !== role));
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold text-zinc-900">Reconnaissance faciale</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Choisissez les grades qui doivent confirmer leur visage après le mot de passe.
        </p>
      </div>

      <Alert>
        <Info />
        <AlertTitle>À quoi sert ce réglage</AlertTitle>
        <AlertDescription>
          Un compte qui n&apos;arrive pas à inscrire son visage (pas de caméra, mauvaise
          lumière) ne peut plus accéder à l&apos;application. Décocher son grade ici le
          débloque immédiatement, sans redéploiement. L&apos;Admin n&apos;est jamais
          soumis au facial : il doit toujours pouvoir revenir sur cet écran.
        </AlertDescription>
      </Alert>

      {isLoading && <p className="text-sm text-zinc-500">Chargement…</p>}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {data?.roles_reglables.map((role) => {
          const actif = roles.includes(role);
          return (
            <Card key={role}>
              <CardContent className="py-4">
                {/* Association implicite (case imbriquée dans le label) : Base UI
                    remplace l'id de la case par le sien, un htmlFor ne
                    désignerait donc rien et le texte ne serait pas cliquable. */}
                <label className="flex cursor-pointer select-none items-start gap-3">
                  <Checkbox
                    checked={actif}
                    onCheckedChange={(checked) => toggle(role, checked === true)}
                    className="mt-0.5"
                  />
                  <span id={`role-${role}-label`}>
                    <span className="flex items-center gap-1.5 font-medium text-zinc-900">
                      {role}
                      {actif && <ShieldCheck className="size-4 text-emerald-600" />}
                    </span>
                    <span className="mt-0.5 block text-xs text-zinc-500">
                      {DESCRIPTIONS[role] ?? ""}
                    </span>
                  </span>
                </label>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="flex items-center gap-3">
        <Button
          onClick={() => roles && update.mutate(roles)}
          disabled={!modifie || update.isPending}
        >
          {update.isPending ? "Enregistrement…" : "Enregistrer"}
        </Button>
        {modifie && <span className="text-xs text-amber-600">Modifications non enregistrées</span>}
        {update.isSuccess && !modifie && (
          <span className="text-xs text-emerald-600">Réglage enregistré.</span>
        )}
      </div>
    </div>
  );
}
