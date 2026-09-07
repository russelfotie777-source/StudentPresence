"use client";

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useLogin } from "@/hooks/use-auth";

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
  const login = useLogin();
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    login.mutate({ phone, password }, { onSuccess: () => router.replace("/dashboard") });
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
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="password">Mot de passe</Label>
            <Input
              id="password"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>
          <Button type="submit" disabled={login.isPending}>
            {login.isPending ? "Connexion…" : "Se connecter"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
