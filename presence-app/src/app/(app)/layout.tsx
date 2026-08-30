"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { RoleBadge } from "@/components/user-badge";
import { useLogout, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { data, isLoading, isError } = useMe();
  const logout = useLogout();
  const user = data?.user;

  useEffect(() => {
    if (!getToken()) {
      router.replace("/login");
      return;
    }
    if (isError) {
      router.replace("/login");
      return;
    }
    if (!isLoading && user && user.role !== "Etudiant" && user.validation_status !== "approved") {
      router.replace("/validation-en-attente");
    }
  }, [isLoading, isError, user, router]);

  if (isLoading || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-zinc-50 text-sm text-zinc-500">
        Chargement…
      </div>
    );
  }

  return (
    <div className="flex min-h-screen flex-col bg-zinc-50">
      <header className="flex items-center justify-between border-b bg-white px-4 py-3 shadow-sm">
        <div className="flex flex-col">
          <span className="text-sm font-semibold text-zinc-900">{user.name}</span>
          <div className="flex items-center gap-2">
            <RoleBadge role={user.effective_role} />
            {user.salle && (
              <span className="text-xs text-zinc-500">{user.salle.nom}</span>
            )}
          </div>
        </div>
        <Button
          variant="ghost"
          size="sm"
          onClick={() => {
            logout.mutate();
            router.replace("/login");
          }}
        >
          Déconnexion
        </Button>
      </header>
      <main className="flex flex-1 flex-col px-4 py-4">{children}</main>
    </div>
  );
}
