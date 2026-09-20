"use client";

import { useState } from "react";
import Link from "next/link";
import { ArrowLeft, CheckCircle2 } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Champ, Erreur } from "@/components/compte/formulaires";
import { useDemanderCodeMotDePasse, useReinitialiserMotDePasse } from "@/hooks/use-compte";
import { ApiError } from "@/lib/api-client";

const inputClass = "h-12 rounded-xl text-base";

/**
 * Mot de passe oublié : l'identifiant, puis le code reçu à l'adresse
 * vérifiée du compte avec le nouveau mot de passe. Sans adresse vérifiée,
 * le serveur renvoie vers l'administration.
 */
export default function MotDePasseOubliePage() {
  const demander = useDemanderCodeMotDePasse();
  const reinitialiser = useReinitialiserMotDePasse();
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [motDePasse, setMotDePasse] = useState("");
  const [confirmation, setConfirmation] = useState("");

  const decalage =
    confirmation !== "" && confirmation !== motDePasse ? "Les deux saisies ne correspondent pas." : undefined;

  if (reinitialiser.isSuccess) {
    return (
      <div className="flex flex-col items-center gap-5 text-center">
        <CheckCircle2 className="h-12 w-12 text-success" />
        <div>
          <h2 className="font-display text-2xl font-bold tracking-tight text-ink-900">Mot de passe modifié</h2>
          <p className="mt-2 text-sm text-ink-500">Connectez-vous avec votre nouveau mot de passe.</p>
        </div>
        <Link
          href="/login"
          className={buttonVariants({ className: "h-12 w-full rounded-xl text-base font-medium" })}
        >
          Se connecter
        </Link>
      </div>
    );
  }

  if (demander.isSuccess) {
    return (
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (decalage) return;
          reinitialiser.mutate({ phone, code, mot_de_passe: motDePasse, mot_de_passe_confirmation: confirmation });
        }}
        className="flex flex-col gap-4"
      >
        <div>
          <h2 className="font-display text-2xl font-bold tracking-tight text-ink-900">Code envoyé</h2>
          <p className="mt-3 text-sm text-ink-900">
            Code envoyé à <span className="font-medium">{demander.data.email_masque}</span>.
          </p>
          <p className="mt-1.5 text-sm text-ink-500">Il est valable trente minutes.</p>
        </div>
        <Erreur message={erreurGenerale(reinitialiser.error, ["code", "mot_de_passe"])} />
        <Champ label="Code reçu" htmlFor="code" error={erreurChamp(reinitialiser.error, "code")}>
          <Input
            id="code"
            inputMode="numeric"
            autoComplete="one-time-code"
            pattern="[0-9]{6}"
            maxLength={6}
            required
            placeholder="000000"
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))}
            className={`${inputClass} text-center font-mono text-xl tracking-[.4em]`}
          />
        </Champ>
        <Champ
          label="Nouveau mot de passe"
          htmlFor="mdp"
          error={erreurChamp(reinitialiser.error, "mot_de_passe")}
          aide="Huit caractères au moins."
        >
          <Input
            id="mdp"
            type="password"
            autoComplete="new-password"
            required
            minLength={8}
            value={motDePasse}
            onChange={(e) => setMotDePasse(e.target.value)}
            className={inputClass}
          />
        </Champ>
        <Champ label="Confirmez le nouveau mot de passe" htmlFor="mdp-confirmation" error={decalage}>
          <Input
            id="mdp-confirmation"
            type="password"
            autoComplete="new-password"
            required
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            className={inputClass}
          />
        </Champ>
        <Button
          type="submit"
          disabled={reinitialiser.isPending || code.length !== 6}
          className="h-12 rounded-xl text-base font-medium"
        >
          {reinitialiser.isPending ? "Enregistrement…" : "Changer le mot de passe"}
        </Button>
        <button
          type="button"
          onClick={() => demander.reset()}
          className="self-center text-sm text-ink-500 underline-offset-2 hover:underline"
        >
          Je n&apos;ai rien reçu
        </button>
      </form>
    );
  }

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        demander.mutate({ phone });
      }}
      className="flex flex-col gap-4"
    >
      <div>
        <h2 className="font-display text-2xl font-bold tracking-tight text-ink-900">Mot de passe oublié</h2>
        <p className="mt-3 text-sm text-ink-500">
          Un code partira à l&apos;adresse e-mail de votre compte.
        </p>
      </div>
      <Erreur message={erreurGenerale(demander.error, ["phone"])} />
      <Champ label="Matricule ou téléphone" htmlFor="phone" error={erreurChamp(demander.error, "phone")}>
        <Input
          id="phone"
          autoComplete="username"
          required
          placeholder="24I01234"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          className={inputClass}
        />
      </Champ>
      <Button type="submit" disabled={demander.isPending} className="h-12 rounded-xl text-base font-medium">
        {demander.isPending ? "Envoi du code…" : "Recevoir un code"}
      </Button>
      <Link
        href="/login"
        className="inline-flex items-center gap-1.5 self-center text-sm text-ink-500 underline-offset-2 hover:underline"
      >
        <ArrowLeft className="h-4 w-4" />
        Retour à la connexion
      </Link>
    </form>
  );
}

function erreurGenerale(error: unknown, champs: string[]): string | null {
  if (!(error instanceof ApiError)) {
    return error ? "La requête n'a pas abouti. Vérifiez votre connexion et réessayez." : null;
  }
  return champs.some((c) => error.errors?.[c]?.length) ? null : error.message;
}

function erreurChamp(error: unknown, champ: string): string | undefined {
  return error instanceof ApiError ? error.errors?.[champ]?.[0] : undefined;
}
