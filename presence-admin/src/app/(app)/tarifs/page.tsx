"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { motion } from "motion/react";
import { Search, Check, Wallet, Info } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { apiFetch, ApiError } from "@/lib/api-client";

interface NiveauAvecTarif {
  id: number;
  nom: string;
  tarif_heure?: { tarif_heure: string } | null;
}

export default function TarifsPage() {
  const { data: niveaux, isLoading } = useQuery({
    queryKey: ["tarifs-heures"],
    queryFn: () => apiFetch<NiveauAvecTarif[]>("/api/tarifs-heures"),
  });

  const [recherche, setRecherche] = useState("");

  const visibles = useMemo(() => {
    if (!niveaux) {
      return [];
    }
    const terme = recherche.trim().toLowerCase();

    return terme ? niveaux.filter((n) => n.nom.toLowerCase().includes(terme)) : niveaux;
  }, [niveaux, recherche]);

  const sansTarif = niveaux?.filter((n) => !n.tarif_heure).length ?? 0;

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Tarifs horaires
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Montant payé par heure enseignée, défini par niveau. Il sert au calcul du salaire de
          chaque enseignant à partir de ses heures réellement effectuées.
        </p>
      </div>

      {sansTarif > 0 && (
        <div className="flex items-start gap-2.5 rounded-xl border border-border bg-muted/60 px-3.5 py-3 text-[13px] text-muted-foreground">
          <Info className="mt-0.5 size-4 shrink-0 text-warning-foreground" />
          <span>
            {sansTarif} {sansTarif > 1 ? "niveaux n'ont" : "niveau n'a"} pas encore de tarif :
            les séances correspondantes sont payées 0.
          </span>
        </div>
      )}

      {niveaux && niveaux.length > 8 && (
        <div className="relative max-w-sm">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Filtrer par niveau…"
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
            className="h-10 rounded-xl pl-9"
          />
        </div>
      )}

      {isLoading && (
        <div className="flex flex-col gap-2">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} className="h-[60px] rounded-xl" />
          ))}
        </div>
      )}

      {niveaux && visibles.length === 0 && (
        <p className="py-10 text-center text-[13px] text-muted-foreground">
          Aucun niveau ne correspond à « {recherche} ».
        </p>
      )}

      <div className="flex flex-col gap-2">
        {visibles.map((n) => (
          <LigneTarif key={n.id} niveau={n} />
        ))}
      </div>
    </div>
  );
}

function LigneTarif({ niveau }: { niveau: NiveauAvecTarif }) {
  const enregistre = niveau.tarif_heure?.tarif_heure ?? "";
  const [valeur, setValeur] = useState(enregistre);
  const queryClient = useQueryClient();

  // Comparaison numérique : « 3000 » et « 3000.00 » sont le même tarif, et le
  // serveur renvoie la seconde forme. Sans ça, la ligne se déclarerait
  // modifiée en permanence juste après un enregistrement réussi.
  const modifie = Number(valeur || 0) !== Number(enregistre || 0);

  const enregistrer = useMutation({
    mutationFn: () =>
      apiFetch(`/api/tarifs-heures/${niveau.id}`, {
        method: "PUT",
        body: JSON.stringify({ tarif_heure: valeur }),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tarifs-heures"] });
      toast.success(`Tarif de ${niveau.nom} enregistré.`);
    },
    onError: (error) =>
      toast.error(error instanceof ApiError ? error.message : "L'enregistrement a échoué."),
  });

  return (
    <motion.div
      initial={{ opacity: 0, y: 6 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
      className="flex flex-col gap-3 rounded-xl border border-border bg-card p-3.5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
    >
      <div className="flex min-w-0 items-center gap-2.5">
        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
          <Wallet className="size-4 text-muted-foreground" />
        </div>
        <span className="truncate text-sm font-medium text-foreground">{niveau.nom}</span>
        {!niveau.tarif_heure && (
          <span className="shrink-0 rounded-full bg-warning/20 px-2 py-0.5 text-[10px] font-medium text-warning-foreground">
            non défini
          </span>
        )}
      </div>

      <div className="flex shrink-0 items-center gap-2">
        <div className="relative">
          <Input
            type="number"
            min={0}
            step="0.01"
            inputMode="decimal"
            value={valeur}
            placeholder="0"
            onChange={(e) => setValeur(e.target.value)}
            aria-label={`Tarif horaire pour ${niveau.nom}`}
            className="h-10 w-40 rounded-lg pr-16 text-right tabular-nums"
          />
          <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground">
            FCFA/h
          </span>
        </div>
        <Button
          size="sm"
          variant={modifie ? "default" : "outline"}
          className="h-10 gap-1.5"
          // Un bouton toujours actif ne dit pas si la valeur affichée est
          // celle enregistrée : ici il ne s'allume que s'il y a un écart réel.
          disabled={!modifie || enregistrer.isPending}
          onClick={() => enregistrer.mutate()}
        >
          <Check className="size-3.5" />
          {enregistrer.isPending ? "…" : modifie ? "Enregistrer" : "À jour"}
        </Button>
      </div>
    </motion.div>
  );
}
