"use client";

import { Suspense, useEffect, useState } from "react";
import { Eye, EyeOff } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useLogin, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

export default function LoginPage() {
  return (
    <Suspense fallback={null}>
      <LoginForm />
    </Suspense>
  );
}

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  // Renseigné par api-client lorsqu'un appel se heurte à un jeton périmé :
  // sans cette explication, l'admin se retrouverait sur l'écran de connexion
  // sans savoir pourquoi il a été éjecté.
  const sessionExpiree = searchParams.get("session") === "expiree";
  // Page réellement demandée avant l'éjection : y revenir évite de faire
  // renaviguer l'admin à la main après chaque expiration de session.
  const retour = searchParams.get("retour");
  const login = useLogin();
  // Revenir en arrière après s'être connecté, ou rouvrir un onglet sur
  // /login, affichait un formulaire de connexion à quelqu'un qui l'est déjà.
  const { data: session } = useMe();
  const dejaConnecte = !!getToken() && session?.user.role === "Admin";
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [motDePasseVisible, setMotDePasseVisible] = useState(false);

  useEffect(() => {
    if (dejaConnecte) {
      router.replace("/dashboard");
    }
  }, [dejaConnecte, router]);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    login.mutate(
      { phone, password },
      {
        // Une destination doit rester interne : une valeur venue de l'URL ne
        // doit pas pouvoir servir à rediriger vers un site tiers après une
        // connexion réussie.
        onSuccess: () =>
          router.replace(retour?.startsWith("/") && !retour.startsWith("//") ? retour : "/dashboard"),
      },
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Connexion administrateur</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {sessionExpiree && !login.error && (
            <Alert>
              <AlertDescription>
                Votre session a expiré après 12 heures. Reconnectez-vous pour continuer.
              </AlertDescription>
            </Alert>
          )}
          {login.error && (
            <Alert variant="destructive">
              <AlertDescription>{login.error.message}</AlertDescription>
            </Alert>
          )}
          <div className="flex flex-col gap-2">
            <Label htmlFor="phone">Téléphone</Label>
            <Input
              id="phone"
              type="tel"
              name="username"
              autoComplete="username"
              autoFocus
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="password">Mot de passe</Label>
            <div className="relative">
              <Input
                id="password"
                type={motDePasseVisible ? "text" : "password"}
                name="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="pr-10"
              />
              <button
                type="button"
                onClick={() => setMotDePasseVisible((v) => !v)}
                className="absolute right-0 top-0 flex h-full w-10 items-center justify-center text-muted-foreground"
                aria-label={motDePasseVisible ? "Masquer le mot de passe" : "Afficher le mot de passe"}
              >
                {motDePasseVisible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
          </div>
          <Button type="submit" disabled={login.isPending}>
            {login.isPending ? "Connexion…" : "Se connecter"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
