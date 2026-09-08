"use client";

import { Sparkles, GraduationCap, CalendarClock } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";

/**
 * Emplacement réservé pour l'assistant IA (inscription des étudiants,
 * programmation des cours). Rien n'est simulé ici — pas de faux échange, pas
 * de logique qui fait semblant de fonctionner : seulement l'affordance dans
 * la coquille, pour que son intégration future n'exige pas de repenser la
 * disposition de l'écran une deuxième fois.
 */
export function AssistantPanel() {
  return (
    <Sheet>
      <SheetTrigger
        render={
          <Button variant="outline" size="sm" className="gap-1.5">
            <Sparkles className="size-3.5" />
            <span className="hidden sm:inline">Assistant IA</span>
          </Button>
        }
      />
      <SheetContent side="right" className="w-80 bg-card text-card-foreground sm:w-96">
        <div className="flex h-full flex-col p-5">
          <div className="flex items-center gap-2.5">
            <div className="flex size-9 items-center justify-center rounded-xl bg-primary/10">
              <Sparkles className="size-[18px] text-primary" />
            </div>
            <div>
              <p className="text-sm font-semibold text-foreground">Assistant IA</p>
              <p className="text-xs text-muted-foreground">Bientôt disponible</p>
            </div>
          </div>

          <div className="mt-8 flex flex-1 flex-col items-center justify-center gap-4 text-center">
            <div className="flex flex-col gap-2.5">
              <FonctionAVenir
                icon={GraduationCap}
                texte="Inscrire un étudiant en décrivant simplement sa situation"
              />
              <FonctionAVenir
                icon={CalendarClock}
                texte="Programmer une séance ou générer un emploi du temps par le dialogue"
              />
            </div>
            <p className="max-w-[260px] text-xs leading-relaxed text-muted-foreground">
              Cet assistant s&apos;intégrera ici, dans ce même panneau — rien à
              reconfigurer d&apos;ici là.
            </p>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

function FonctionAVenir({
  icon: Icon,
  texte,
}: {
  icon: typeof GraduationCap;
  texte: string;
}) {
  return (
    <div className="flex items-center gap-2.5 rounded-xl border border-dashed border-border px-3.5 py-2.5 text-left">
      <Icon className="size-4 shrink-0 text-muted-foreground" />
      <span className="text-[12.5px] text-muted-foreground">{texte}</span>
    </div>
  );
}
