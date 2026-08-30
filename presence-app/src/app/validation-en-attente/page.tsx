"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Clock, LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useLogout, useMe, useRequestValidation } from "@/hooks/use-auth";

export default function ValidationEnAttentePage() {
  const router = useRouter();
  const { data, isLoading } = useMe();
  const requestValidation = useRequestValidation();
  const logout = useLogout();

  const user = data?.user;

  useEffect(() => {
    if (!isLoading && user && user.validation_status === "approved") {
      router.replace("/dashboard");
    }
  }, [isLoading, user, router]);

  if (isLoading || !user) return null;

  return (
    <div className="mx-auto flex min-h-screen w-full max-w-sm flex-col items-center justify-center gap-6 px-6 text-center">
      <div className="flex h-16 w-16 items-center justify-center rounded-full bg-warning/20">
        <Clock className="h-7 w-7 text-warning-foreground" />
      </div>

      <div>
        <h1 className="text-xl font-semibold text-foreground">
          Compte en attente de validation
        </h1>
        {user.validation_status === "none" ? (
          <p className="mt-2 text-sm text-muted-foreground">
            Votre compte {user.role === "Delegue" ? "Délégué" : "Enseignant"} doit être soumis
            pour validation par un administrateur avant de pouvoir accéder à l&apos;application.
          </p>
        ) : (
          <p className="mt-2 text-sm text-muted-foreground">
            Votre demande a été transmise. Un administrateur doit encore l&apos;approuver —
            revenez plus tard.
          </p>
        )}
      </div>

      {user.validation_status === "none" && (
        <Button
          onClick={() => requestValidation.mutate()}
          disabled={requestValidation.isPending}
          className="h-12 w-full rounded-xl text-base font-medium"
        >
          {requestValidation.isPending ? "Envoi…" : "Soumettre pour validation"}
        </Button>
      )}

      <Button
        variant="ghost"
        className="gap-2 text-muted-foreground"
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
