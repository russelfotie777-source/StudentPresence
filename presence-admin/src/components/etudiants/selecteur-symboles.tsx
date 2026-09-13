"use client";

import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useChangerSymboles, type Symboles } from "@/hooks/use-feuille-presence";

/**
 * Coche et croix rouge, ou +1 / −1 : la notation des présences, choisie
 * une fois pour la grille et pour les PDF. Enregistrée côté serveur dès le
 * clic, elle vaut pour tous les admins.
 */
export function SelecteurSymboles({ valeur, compact = false }: { valeur: Symboles; compact?: boolean }) {
  const changer = useChangerSymboles();

  return (
    <Tabs value={valeur} onValueChange={(v) => v !== valeur && changer.mutate(v as Symboles)}>
      <TabsList aria-label="Notation des présences" className={compact ? "h-9" : undefined}>
        <TabsTrigger value="coche" className="gap-1.5 font-display font-bold tabular-nums" title="Coche pour présent, croix rouge pour absent">
          <span className="text-success">✓</span>
          <span className="text-destructive">✗</span>
          {!compact && <span className="ml-1 font-sans font-medium">Coche / croix</span>}
        </TabsTrigger>
        <TabsTrigger value="valeur" className="gap-1.5 font-display font-bold tabular-nums" title="+1 pour présent, −1 pour absent">
          <span className="text-success">+1</span>
          <span className="text-destructive">−1</span>
          {!compact && <span className="ml-1 font-sans font-medium">Plus / moins</span>}
        </TabsTrigger>
      </TabsList>
    </Tabs>
  );
}
