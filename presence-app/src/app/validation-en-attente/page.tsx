"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
    <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-violet-950 via-violet-900 to-black px-4">
      <Card className="w-full max-w-sm border-violet-800/40 bg-white/5 text-center backdrop-blur">
        <CardHeader>
          <CardTitle className="text-white">Compte en attente de validation</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {user.validation_status === "none" ? (
            <>
              <p className="text-sm text-violet-200">
                Votre compte {user.role === "Delegue" ? "Délégué" : "Enseignant"} doit être
                soumis pour validation par un administrateur avant de pouvoir accéder à
                l&apos;application.
              </p>
              <Button
                onClick={() => requestValidation.mutate()}
                disabled={requestValidation.isPending}
              >
                {requestValidation.isPending ? "Envoi…" : "Soumettre pour validation"}
              </Button>
            </>
          ) : (
            <p className="text-sm text-violet-200">
              Votre demande a été transmise. Un administrateur doit encore l&apos;approuver —
              revenez plus tard.
            </p>
          )}
          <Button variant="ghost" className="text-violet-200" onClick={() => logout.mutate()}>
            Se déconnecter
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
