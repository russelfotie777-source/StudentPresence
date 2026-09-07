"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";
import { AdminNav } from "@/components/admin-nav";
import { useLogout, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { data, isLoading, isError } = useMe();
  const logout = useLogout();
  const user = data?.user;

  useEffect(() => {
    if (!getToken() || isError) {
      // `isError` signifie que le jeton présent a été rejeté : la session a
      // expiré. Sans jeton du tout, l'utilisateur n'était simplement pas
      // connecté et n'a rien à se faire expliquer. Les deux chemins de
      // redirection doivent porter la même raison, sinon celui qui gagne la
      // course efface le message de l'autre.
      const params = new URLSearchParams();
      if (isError) {
        params.set("session", "expiree");
      }
      if (pathname && pathname !== "/dashboard") {
        params.set("retour", pathname);
      }

      const requete = params.toString();
      router.replace(requete ? `/login?${requete}` : "/login");
      return;
    }
    if (!isLoading && user && user.role !== "Admin") {
      router.replace("/login");
    }
  }, [isLoading, isError, user, router, pathname]);

  if (isLoading || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  const initials = user.name
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <div className="flex min-h-screen bg-background">
      <aside className="hidden w-64 shrink-0 border-r border-border bg-sidebar md:flex md:flex-col">
        <div className="flex items-center gap-2.5 border-b border-border px-4 py-4">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground">
            ✓
          </div>
          <div>
            <p className="text-sm font-semibold text-foreground">Présence</p>
            <p className="text-xs text-muted-foreground">Administration</p>
          </div>
        </div>
        <AdminNav />
      </aside>
      <div className="flex flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-border bg-card px-6 py-3">
          <div className="flex items-center gap-2.5">
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-accent text-xs font-medium text-accent-foreground">
              {initials}
            </div>
            <span className="text-sm font-medium text-foreground">{user.name}</span>
          </div>
          <Button
            variant="ghost"
            size="sm"
            className="gap-1.5 text-muted-foreground"
            onClick={() => {
              logout.mutate();
              router.replace("/login");
            }}
          >
            <LogOut className="h-4 w-4" />
            Déconnexion
          </Button>
        </header>
        <main className="flex flex-1 flex-col gap-4 p-6">{children}</main>
      </div>
    </div>
  );
}
