"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useLogin } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";

export default function LoginPage() {
  const router = useRouter();
  const login = useLogin();
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    login.mutate(
      { phone, password },
      {
        onSuccess: (data) => {
          if (data.user.role !== "Etudiant" && data.user.validation_status !== "approved") {
            router.replace("/validation-en-attente");
          } else {
            router.replace("/dashboard");
          }
        },
      },
    );
  }

  const errorMessage =
    login.error instanceof ApiError
      ? (login.error.errors?.phone?.[0] ?? login.error.message)
      : null;

  return (
    <Card className="border-violet-800/40 bg-white/5 backdrop-blur">
      <CardHeader>
        <CardTitle className="text-white">Connexion</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {errorMessage && (
            <Alert variant="destructive">
              <AlertDescription>{errorMessage}</AlertDescription>
            </Alert>
          )}

          <div className="flex flex-col gap-2">
            <Label htmlFor="phone" className="text-violet-100">
              Téléphone
            </Label>
            <Input
              id="phone"
              type="tel"
              autoComplete="tel"
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="border-violet-700/50 bg-white/10 text-white placeholder:text-violet-300"
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="password" className="text-violet-100">
              Mot de passe
            </Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="border-violet-700/50 bg-white/10 text-white placeholder:text-violet-300"
            />
          </div>

          <Button type="submit" disabled={login.isPending} className="mt-2">
            {login.isPending ? "Connexion…" : "Se connecter"}
          </Button>

          <p className="text-center text-sm text-violet-200">
            Pas encore de compte ?{" "}
            <Link href="/register" className="font-medium text-white underline">
              S&apos;inscrire
            </Link>
          </p>
        </form>
      </CardContent>
    </Card>
  );
}
