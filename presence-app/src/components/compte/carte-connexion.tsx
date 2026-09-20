"use client";

import { useState } from "react";
import { AtSign, ChevronRight, KeyRound, Phone } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { FormulaireEmail, FormulaireMotDePasse, FormulaireTelephone } from "@/components/compte/formulaires";
import type { MeResponse } from "@/types/api";

type Volet = "mot-de-passe" | "telephone" | "email" | null;

/**
 * Ce que la personne tient à jour elle-même sur son compte : mot de passe,
 * numéro (l'enseignant, dont c'est l'identifiant) et adresse e-mail. Le
 * matricule, lui, ne se change pas — il n'apparaît pas ici.
 */
export function CarteConnexion({ me }: { me: MeResponse }) {
  const user = me.user;
  const [volet, setVolet] = useState<Volet>(null);
  const enseignant = user.role === "Enseignant";
  const emailEnAttente = me.email_en_attente ?? null;

  const etatEmail = user.email_verifie
    ? user.email!
    : emailEnAttente
      ? `${emailEnAttente} · code à saisir`
      : user.email
        ? `${user.email} · à vérifier`
        : "Aucune adresse";

  return (
    <>
      <div className="overflow-hidden rounded-2xl border border-border bg-card">
        <Ligne icon={KeyRound} label="Mot de passe" valeur="••••••••" onClick={() => setVolet("mot-de-passe")} />
        {enseignant && (
          <Ligne
            icon={Phone}
            label="Numéro"
            valeur={user.phone.startsWith("ENS") ? `${user.phone} · provisoire` : user.phone}
            onClick={() => setVolet("telephone")}
          />
        )}
        <Ligne
          icon={AtSign}
          label="E-mail"
          valeur={etatEmail}
          attention={!user.email_verifie}
          onClick={() => setVolet("email")}
        />
      </div>

      <Dialog open={volet !== null} onOpenChange={(o) => !o && setVolet(null)}>
        <DialogContent className="rounded-2xl sm:max-w-md">
          {volet === "mot-de-passe" && (
            <>
              <DialogHeader>
                <DialogTitle>Changer de mot de passe</DialogTitle>
                <DialogDescription>Vos autres appareils seront déconnectés.</DialogDescription>
              </DialogHeader>
              <FormulaireMotDePasse onDone={() => setVolet(null)} />
            </>
          )}
          {volet === "telephone" && (
            <>
              <DialogHeader>
                <DialogTitle>Changer de numéro</DialogTitle>
                <DialogDescription>Votre numéro est votre identifiant de connexion.</DialogDescription>
              </DialogHeader>
              <FormulaireTelephone actuel={user.phone} onDone={() => setVolet(null)} />
            </>
          )}
          {volet === "email" && (
            <>
              <DialogHeader>
                <DialogTitle>{user.email_verifie ? "Changer d'adresse e-mail" : "Adresse e-mail"}</DialogTitle>
                <DialogDescription>
                  {user.email_verifie
                    ? `${user.email} reste en place tant que la nouvelle adresse n'est pas confirmée.`
                    : "Elle sert à retrouver un mot de passe oublié."}
                </DialogDescription>
              </DialogHeader>
              <FormulaireEmail
                emailActuel={user.email}
                emailVerifie={user.email_verifie}
                emailEnAttente={emailEnAttente}
                onDone={() => setVolet(null)}
              />
            </>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}

function Ligne({
  icon: Icon,
  label,
  valeur,
  attention,
  onClick,
}: {
  icon: typeof Phone;
  label: string;
  valeur: string;
  attention?: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex w-full items-center gap-3 border-b border-border px-4 py-3.5 text-left last:border-b-0 hover:bg-muted/50"
    >
      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted">
        <Icon className="h-4 w-4 text-muted-foreground" />
      </div>
      <div className="flex min-w-0 flex-1 items-center justify-between gap-3">
        <span className="shrink-0 text-sm text-muted-foreground">{label}</span>
        <span className={`truncate text-sm ${attention ? "text-ink-500" : "font-medium text-foreground"}`}>
          {valeur}
        </span>
      </div>
      <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
    </button>
  );
}
