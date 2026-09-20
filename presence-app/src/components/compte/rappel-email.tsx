"use client";

import Link from "next/link";
import { AtSign, ChevronRight } from "lucide-react";
import type { User } from "@/types/api";

/**
 * Tant qu'aucune adresse n'est vérifiée, un mot de passe oublié passe par
 * l'administration : on le rappelle sur l'accueil, en une ligne, avec le
 * chemin pour y remédier.
 */
export function RappelEmail({ user }: { user: User }) {
  if (user.email_verifie) return null;

  return (
    <Link
      href="/profil"
      className="mb-5 flex items-center gap-3 rounded-2xl border border-line bg-card px-4 py-3 text-xs"
    >
      <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted">
        <AtSign className="size-[18px] text-muted-foreground" />
      </div>
      <span className="min-w-0 flex-1 leading-relaxed text-ink-500">
        <span className="font-semibold text-ink-900">Ajoutez une adresse e-mail</span> pour
        retrouver votre mot de passe sans passer par l&apos;administration.
      </span>
      <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
    </Link>
  );
}
