"use client";

import { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { LogOut, Menu, Settings } from "lucide-react";
import { Button } from "@/components/ui/button";
import { AdminNav } from "@/components/admin-nav";
import { AssistantPanel } from "@/components/assistant-panel";
import { ThemeToggle } from "@/components/theme-toggle";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useLogout, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";
import { titrePage } from "@/lib/titre-page";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { data, isLoading, isError } = useMe();
  const logout = useLogout();
  const user = data?.user;
  const [navOuvert, setNavOuvert] = useState(false);

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

  function deconnexion() {
    logout.mutate();
    router.replace("/login");
  }

  return (
    <div className="flex min-h-screen bg-background">
      <aside className="hidden w-64 shrink-0 flex-col border-r border-sidebar-border bg-sidebar lg:flex">
        <Marque />
        <div className="flex-1 overflow-y-auto py-4">
          <AdminNav />
        </div>
      </aside>

      <Sheet open={navOuvert} onOpenChange={setNavOuvert}>
        <SheetContent side="left" className="lg:hidden">
          <Marque />
          <div className="flex-1 overflow-y-auto py-4">
            <AdminNav onNavigate={() => setNavOuvert(false)} />
          </div>
        </SheetContent>
      </Sheet>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-border bg-card/80 px-4 py-3 backdrop-blur-md sm:px-6">
          <div className="flex min-w-0 items-center gap-2.5">
            <Sheet open={navOuvert} onOpenChange={setNavOuvert}>
              <SheetTrigger
                render={
                  <Button variant="ghost" size="icon" className="shrink-0 lg:hidden" aria-label="Ouvrir le menu" />
                }
              >
                <Menu className="size-[18px]" />
              </SheetTrigger>
            </Sheet>
            <h1 className="truncate font-display text-[17px] font-semibold text-foreground">
              {titrePage(pathname ?? "")}
            </h1>
          </div>

          <div className="flex shrink-0 items-center gap-2">
            <AssistantPanel />
            <ThemeToggle />

            <DropdownMenu>
              <DropdownMenuTrigger
                render={<button type="button" className="ml-1 rounded-full outline-none focus-visible:ring-3 focus-visible:ring-ring/50" />}
              >
                <Avatar className="size-8 ring-1 ring-border">
                  <AvatarFallback>{initiales(user.name)}</AvatarFallback>
                </Avatar>
              </DropdownMenuTrigger>
              <DropdownMenuContent>
                <DropdownMenuLabel>
                  <p className="truncate text-[13px] font-medium text-foreground">{user.name}</p>
                  <p className="text-xs font-normal text-muted-foreground">Administrateur</p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  render={<a href="/reconnaissance-faciale" />}
                >
                  <Settings />
                  Réglages de sécurité
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onClick={deconnexion}>
                  <LogOut />
                  Déconnexion
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        <main className="flex flex-1 flex-col gap-4 p-4 sm:p-6">{children}</main>
      </div>
    </div>
  );
}

function Marque() {
  return (
    <div className="flex items-center gap-2.5 border-b border-sidebar-border px-4 py-4">
      <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-sm">
        P
      </div>
      <div>
        <p className="font-display text-[13.5px] font-semibold leading-tight text-sidebar-foreground">
          Présence
        </p>
        <p className="text-[11px] text-sidebar-foreground/50">Administration</p>
      </div>
    </div>
  );
}

function initiales(nom: string): string {
  return nom
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}
