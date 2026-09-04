"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
          if (data.requires_face) {
            router.replace("/face");
          } else if (data.user.role !== "Etudiant" && data.user.validation_status !== "approved") {
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
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      <div>
        <h2 className="font-display text-[28px] font-bold leading-tight tracking-tight text-ink-900">
          Bon retour.
        </h2>
        <p className="mt-1.5 text-[15px] text-ink-500">
          Connectez-vous pour pointer votre présence du jour.
        </p>
      </div>

      {errorMessage && (
        <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="phone">Matricule</Label>
        <Input
          id="phone"
          type="text"
          autoComplete="username"
          required
          placeholder="24I01234"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          className="h-12 rounded-xl text-base"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="password">Mot de passe</Label>
        <Input
          id="password"
          type="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="h-12 rounded-xl text-base"
        />
      </div>

      <Button
        type="submit"
        disabled={login.isPending}
        className="mt-1 h-12 rounded-xl text-base font-medium shadow-sm"
      >
        {login.isPending ? "Connexion…" : "Se connecter"}
      </Button>

      <p className="text-center text-sm text-ink-500">
        Pas encore de compte ?{" "}
        <Link href="/register" className="font-bold text-indigo-600">
          S&apos;inscrire
        </Link>
      </p>
    </form>
  );
}
