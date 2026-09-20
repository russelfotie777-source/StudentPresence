"use client";

import { useEffect, useState } from "react";
import { AlertCircle, Eye, EyeOff } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  useChangerMotDePasse,
  useChangerTelephone,
  useDefinirEmail,
  useRenvoyerCode,
  useVerifierEmail,
} from "@/hooks/use-compte";
import { ApiError } from "@/lib/api-client";

/*
 * Les trois formulaires du compte, partagés entre l'écran de première
 * connexion (à plat) et la page Profil (dans une boîte de dialogue).
 */

const inputClass = "h-12 rounded-xl text-base";

export function Champ({
  label,
  htmlFor,
  error,
  aide,
  children,
}: {
  label: string;
  htmlFor: string;
  error?: string;
  aide?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={htmlFor}>{label}</Label>
      {children}
      {error ? (
        <p className="text-xs text-destructive">{error}</p>
      ) : aide ? (
        <p className="text-xs text-ink-500">{aide}</p>
      ) : null}
    </div>
  );
}

function ChampMotDePasse({
  id,
  value,
  onChange,
  autoComplete,
  placeholder,
}: {
  id: string;
  value: string;
  onChange: (v: string) => void;
  autoComplete: "current-password" | "new-password";
  placeholder?: string;
}) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative">
      <Input
        id={id}
        type={visible ? "text" : "password"}
        autoComplete={autoComplete}
        required
        minLength={autoComplete === "new-password" ? 8 : undefined}
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={`${inputClass} pr-11`}
      />
      <button
        type="button"
        aria-label={visible ? "Masquer" : "Afficher"}
        aria-pressed={visible}
        onClick={() => setVisible((v) => !v)}
        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-ink-500 hover:text-ink-900"
      >
        {visible ? <EyeOff size={17} /> : <Eye size={17} />}
      </button>
    </div>
  );
}

export function Erreur({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <div
      role="alert"
      className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive"
    >
      <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
      <span>{message}</span>
    </div>
  );
}

/** Le message général d'une erreur API, sauf s'il ne fait que répéter une erreur de champ déjà affichée. */
function messageGeneral(error: unknown, champs: string[]): string | null {
  if (!(error instanceof ApiError)) {
    return error ? "La requête n'a pas abouti. Vérifiez votre connexion et réessayez." : null;
  }
  const detaille = champs.some((c) => error.errors?.[c]?.length);
  return detaille ? null : error.message;
}

function erreurChamp(error: unknown, champ: string): string | undefined {
  return error instanceof ApiError ? error.errors?.[champ]?.[0] : undefined;
}

// --- mot de passe -----------------------------------------------------------

export function FormulaireMotDePasse({
  onDone,
  libelleBouton = "Enregistrer",
}: {
  onDone?: () => void;
  libelleBouton?: string;
}) {
  const changer = useChangerMotDePasse();
  const [actuel, setActuel] = useState("");
  const [nouveau, setNouveau] = useState("");
  const [confirmation, setConfirmation] = useState("");

  const decalage =
    confirmation !== "" && confirmation !== nouveau
      ? "Les deux saisies ne correspondent pas."
      : undefined;

  function submit(e: React.FormEvent) {
    e.preventDefault();
    if (decalage) return;
    changer.mutate(
      { mot_de_passe_actuel: actuel, mot_de_passe: nouveau, mot_de_passe_confirmation: confirmation },
      {
        onSuccess: (r) => {
          toast.success(r.message);
          onDone?.();
        },
      },
    );
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Erreur message={messageGeneral(changer.error, ["mot_de_passe_actuel", "mot_de_passe"])} />
      <Champ label="Mot de passe actuel" htmlFor="mdp-actuel" error={erreurChamp(changer.error, "mot_de_passe_actuel")}>
        <ChampMotDePasse id="mdp-actuel" value={actuel} onChange={setActuel} autoComplete="current-password" />
      </Champ>
      <Champ
        label="Nouveau mot de passe"
        htmlFor="mdp-nouveau"
        error={erreurChamp(changer.error, "mot_de_passe")}
        aide="Huit caractères au moins."
      >
        <ChampMotDePasse id="mdp-nouveau" value={nouveau} onChange={setNouveau} autoComplete="new-password" />
      </Champ>
      <Champ label="Confirmez le nouveau mot de passe" htmlFor="mdp-confirmation" error={decalage}>
        <ChampMotDePasse id="mdp-confirmation" value={confirmation} onChange={setConfirmation} autoComplete="new-password" />
      </Champ>
      <Button type="submit" disabled={changer.isPending} className="h-12 rounded-xl text-base font-medium">
        {changer.isPending ? "Enregistrement…" : libelleBouton}
      </Button>
    </form>
  );
}

// --- téléphone (enseignant) -------------------------------------------------

export function FormulaireTelephone({ actuel, onDone }: { actuel: string; onDone?: () => void }) {
  const changer = useChangerTelephone();
  const [telephone, setTelephone] = useState(actuel.startsWith("ENS") ? "" : actuel);
  const [motDePasse, setMotDePasse] = useState("");

  function submit(e: React.FormEvent) {
    e.preventDefault();
    changer.mutate(
      { telephone, mot_de_passe_actuel: motDePasse },
      {
        onSuccess: (r) => {
          toast.success(r.message);
          onDone?.();
        },
      },
    );
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Erreur message={messageGeneral(changer.error, ["telephone", "mot_de_passe_actuel"])} />
      <Champ
        label="Numéro de téléphone"
        htmlFor="telephone"
        error={erreurChamp(changer.error, "telephone")}
        aide="Vous vous connecterez avec ce numéro."
      >
        <Input
          id="telephone"
          type="tel"
          autoComplete="tel"
          required
          placeholder="6XX XXX XXX"
          value={telephone}
          onChange={(e) => setTelephone(e.target.value)}
          className={inputClass}
        />
      </Champ>
      <Champ label="Mot de passe actuel" htmlFor="tel-mdp" error={erreurChamp(changer.error, "mot_de_passe_actuel")}>
        <ChampMotDePasse id="tel-mdp" value={motDePasse} onChange={setMotDePasse} autoComplete="current-password" />
      </Champ>
      <Button type="submit" disabled={changer.isPending} className="h-12 rounded-xl text-base font-medium">
        {changer.isPending ? "Enregistrement…" : "Changer de numéro"}
      </Button>
    </form>
  );
}

// --- e-mail -----------------------------------------------------------------

/**
 * Deux temps : l'adresse, puis le code reçu. L'adresse en attente vient du
 * serveur, pour reprendre au code après un rechargement ; une adresse
 * donnée à la création mais jamais confirmée est proposée telle quelle.
 */
export function FormulaireEmail({
  emailActuel,
  emailVerifie,
  emailEnAttente,
  onDone,
  onPlusTard,
}: {
  emailActuel: string | null;
  emailVerifie: boolean;
  emailEnAttente: string | null | undefined;
  onDone?: () => void;
  onPlusTard?: () => void;
}) {
  const definir = useDefinirEmail();
  const renvoyer = useRenvoyerCode();
  const verifier = useVerifierEmail();

  const [email, setEmail] = useState(emailVerifie ? "" : (emailActuel ?? ""));
  const [code, setCode] = useState("");
  const [saisieAdresse, setSaisieAdresse] = useState(!emailEnAttente);
  const adresseVisee = emailEnAttente ?? null;
  const etapeCode = !saisieAdresse && adresseVisee;

  // Pas de renvoi avant une minute : on affiche le compte à rebours plutôt
  // que de laisser cliquer dans le vide.
  const [attente, setAttente] = useState(0);
  useEffect(() => {
    if (attente <= 0) return;
    const t = setTimeout(() => setAttente((a) => a - 1), 1000);
    return () => clearTimeout(t);
  }, [attente]);

  function envoyer(e: React.FormEvent) {
    e.preventDefault();
    definir.mutate(
      { email },
      {
        onSuccess: () => {
          setCode("");
          setSaisieAdresse(false);
          setAttente(60);
        },
      },
    );
  }

  function confirmer(e: React.FormEvent) {
    e.preventDefault();
    verifier.mutate(
      { code },
      {
        onSuccess: (r) => {
          toast.success(r.message);
          onDone?.();
        },
      },
    );
  }

  if (etapeCode) {
    return (
      <form onSubmit={confirmer} className="flex flex-col gap-4">
        <div className="flex flex-col gap-1.5 text-sm">
          <p className="text-ink-900">
            Code envoyé à <span className="font-medium">{adresseVisee}</span>.
          </p>
          <p className="text-ink-500">Rien reçu ? Regardez dans les courriers indésirables.</p>
        </div>
        <Erreur message={messageGeneral(verifier.error ?? renvoyer.error, ["code"])} />
        <Champ label="Code reçu" htmlFor="code" error={erreurChamp(verifier.error ?? renvoyer.error, "code")}>
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
        <Button type="submit" disabled={verifier.isPending || code.length !== 6} className="h-12 rounded-xl text-base font-medium">
          {verifier.isPending ? "Vérification…" : "Vérifier l'adresse"}
        </Button>
        <div className="flex flex-wrap justify-between gap-x-4 gap-y-2 text-sm">
          <button
            type="button"
            disabled={attente > 0 || renvoyer.isPending}
            onClick={() =>
              renvoyer.mutate(undefined, {
                onSuccess: (r) => {
                  toast.success(r.message);
                  setAttente(60);
                },
              })
            }
            className="text-ink-500 underline-offset-2 hover:underline disabled:no-underline disabled:opacity-60"
          >
            {attente > 0 ? `Renvoyer le code (${attente} s)` : "Renvoyer le code"}
          </button>
          <button
            type="button"
            onClick={() => setSaisieAdresse(true)}
            className="text-ink-500 underline-offset-2 hover:underline"
          >
            Changer d&apos;adresse
          </button>
        </div>
      </form>
    );
  }

  return (
    <form onSubmit={envoyer} className="flex flex-col gap-4">
      <Erreur message={messageGeneral(definir.error, ["email"])} />
      <Champ
        label="Adresse e-mail"
        htmlFor="email"
        error={erreurChamp(definir.error, "email")}
        aide="Un code vous y sera envoyé."
      >
        <Input
          id="email"
          type="email"
          autoComplete="email"
          inputMode="email"
          required
          placeholder="prenom.nom@gmail.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className={inputClass}
        />
      </Champ>
      <Button type="submit" disabled={definir.isPending} className="h-12 rounded-xl text-base font-medium">
        {definir.isPending ? "Envoi du code…" : "Recevoir un code"}
      </Button>
      {onPlusTard && (
        <button
          type="button"
          onClick={onPlusTard}
          className="self-center text-sm text-ink-500 underline-offset-2 hover:underline"
        >
          Plus tard
        </button>
      )}
    </form>
  );
}
