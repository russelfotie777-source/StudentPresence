"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";
import { FormulaireEmail, FormulaireMotDePasse } from "@/components/compte/formulaires";
import { useLogout, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

/**
 * Première connexion d'un compte créé par l'administration : le mot de
 * passe remis avec l'identifiant est le même pour tout le monde, il se
 * remplace avant tout le reste. L'adresse e-mail, elle, peut attendre —
 * bloquer ici quelqu'un qui doit pointer dans cinq minutes serait pire
 * que de le relancer plus tard.
 */
export default function PremiereConnexionPage() {
  const router = useRouter();
  const { data, isLoading, isError } = useMe();
  const logout = useLogout();
  const user = data?.user;

  // Le mot de passe changé, « me » est déjà à jour : l'étape suivante en
  // découle, sans état local à synchroniser.
  const etape = !user
    ? null
    : user.doit_changer_mot_de_passe
      ? "mot-de-passe"
      : user.email_verifie
        ? "fini"
        : "email";

  useEffect(() => {
    if (!getToken() || isError) router.replace("/login");
    else if (!isLoading && data?.face_pending) router.replace("/face");
    else if (etape === "fini") router.replace("/dashboard");
  }, [isLoading, isError, data, etape, router]);

  if (isLoading || !user || etape === "fini") return null;

  const etapeEmail = etape === "email";

  return (
    <div className="flex flex-col gap-6">
      <div>
        <p className="text-sm font-semibold text-primary">
          {etapeEmail ? "Étape 2 sur 2" : "Étape 1 sur 2"}
        </p>
        <h2 className="mt-1 font-display text-2xl font-bold tracking-tight text-ink-900">
          {etapeEmail ? "Une adresse pour vous dépanner" : `Bienvenue, ${prenom(user.name)}`}
        </h2>
        <p className="mt-2 text-sm leading-relaxed text-ink-500">
          {etapeEmail
            ? "Si vous oubliez votre mot de passe, c'est à cette adresse que vous recevrez le code pour en choisir un autre. Sans elle, il faudra passer par l'administration."
            : "Le mot de passe qui vous a été remis est le même pour tous les nouveaux comptes. Choisissez-en un qui n'appartient qu'à vous : il protège votre présence en cours."}
        </p>
      </div>

      {etapeEmail ? (
        <FormulaireEmail
          emailActuel={user.email}
          emailVerifie={user.email_verifie}
          emailEnAttente={data?.email_en_attente}
          onDone={() => router.replace("/dashboard")}
          onPlusTard={() => router.replace("/dashboard")}
        />
      ) : (
        <FormulaireMotDePasse libelleBouton="Continuer" />
      )}

      <Button
        variant="ghost"
        className="gap-2 self-center text-muted-foreground"
        onClick={() => {
          logout.mutate();
          router.replace("/login");
        }}
      >
        <LogOut className="h-4 w-4" />
        Se déconnecter
      </Button>
    </div>
  );
}

function prenom(nom: string): string {
  const premier = nom.trim().split(/\s+/)[0] ?? "";
  return premier.charAt(0).toUpperCase() + premier.slice(1).toLowerCase();
}
