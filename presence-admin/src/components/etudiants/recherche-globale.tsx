"use client";

import { useEffect, useRef, useState } from "react";
import { Loader2, Search, X } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { useEtudiants } from "@/hooks/use-etudiants";
import { cn } from "@/lib/utils";
import type { User } from "@/types/api";
import { initiales } from "./grille-presences";

interface Props {
  /** Filtre appliqué aux lignes de la salle affichée, en direct. */
  valeur: string;
  onChange: (valeur: string) => void;
  /** Un étudiant d'une autre salle choisi dans les résultats : la page bascule sur sa salle. */
  onChoisir: (etudiant: User) => void;
  salleAffichee: number | null;
}

/**
 * Un seul champ pour deux gestes : filtrer la salle à l'écran au fil de la
 * frappe, et retrouver un étudiant dont on ne sait pas la salle — les
 * résultats des autres salles apparaissent dessous, un clic y emmène.
 */
export function RechercheGlobale({ valeur, onChange, onChoisir, salleAffichee }: Props) {
  const [ouvert, setOuvert] = useState(false);
  const [terme, setTerme] = useState(valeur);
  const conteneur = useRef<HTMLDivElement>(null);

  // Débounce : la recherche globale interroge l'API, pas la liste locale.
  useEffect(() => {
    const t = setTimeout(() => setTerme(valeur.trim()), 250);
    return () => clearTimeout(t);
  }, [valeur]);

  const recherche = useEtudiants({ search: terme }, terme.length >= 2);
  const ailleurs = (recherche.data?.pages[0]?.data ?? []).filter(
    (u) => u.salle && u.salle.id !== salleAffichee,
  );

  useEffect(() => {
    if (!ouvert) return;
    function fermer(e: MouseEvent) {
      if (!conteneur.current?.contains(e.target as Node)) setOuvert(false);
    }
    document.addEventListener("mousedown", fermer);
    return () => document.removeEventListener("mousedown", fermer);
  }, [ouvert]);

  const montrer = ouvert && terme.length >= 2;

  return (
    <div ref={conteneur} className="relative w-full sm:w-80">
      <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        value={valeur}
        onChange={(e) => {
          onChange(e.target.value);
          setOuvert(true);
        }}
        onFocus={() => setOuvert(true)}
        onKeyDown={(e) => e.key === "Escape" && setOuvert(false)}
        placeholder="Nom ou matricule, toutes salles…"
        aria-label="Rechercher un étudiant"
        className="h-9 rounded-lg pr-8 pl-9"
      />
      {valeur && (
        <button
          type="button"
          aria-label="Effacer la recherche"
          onClick={() => {
            onChange("");
            setOuvert(false);
          }}
          className="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-0.5 text-muted-foreground hover:text-foreground"
        >
          <X className="size-3.5" />
        </button>
      )}

      {montrer && (recherche.isLoading || ailleurs.length > 0) && (
        <div className="absolute top-full left-0 z-30 mt-1.5 w-full overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-md">
          <p className="border-b border-border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            Dans d&apos;autres salles
          </p>
          {recherche.isLoading ? (
            <div className="flex items-center gap-2 px-3 py-2.5 text-xs text-muted-foreground">
              <Loader2 className="size-3.5 animate-spin" /> Recherche…
            </div>
          ) : (
            <ul className="max-h-72 overflow-y-auto py-1">
              {ailleurs.slice(0, 8).map((u) => (
                <li key={u.id}>
                  <button
                    type="button"
                    onClick={() => {
                      onChoisir(u);
                      setOuvert(false);
                    }}
                    className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] hover:bg-accent"
                  >
                    <Avatar className="size-7">
                      <AvatarFallback
                        className={cn(
                          "text-[10px] font-semibold",
                          u.formation === "FM"
                            ? "bg-warning/25 text-warning-foreground"
                            : "bg-accent text-accent-foreground",
                        )}
                      >
                        {initiales(u.name)}
                      </AvatarFallback>
                    </Avatar>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium">{u.name}</span>
                      <span className="block truncate text-[11.5px] text-muted-foreground">
                        {u.phone} · {u.salle?.nom}
                        {u.filiere ? ` · ${u.filiere.nom}` : ""}
                      </span>
                    </span>
                    <span className="text-[11px] text-primary">Ouvrir →</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
