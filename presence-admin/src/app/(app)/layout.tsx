"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { AdminNav } from "@/components/admin-nav";
import { useLogout, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { data, isLoading, isError } = useMe();
  const logout = useLogout();
  const user = data?.user;

  useEffect(() => {
    if (!getToken() || isError) {
      router.replace("/login");
      return;
    }
    if (!isLoading && user && user.role !== "Admin") {
      router.replace("/login");
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
    <div className="flex min-h-screen bg-zinc-50">
      <aside className="hidden w-60 shrink-0 border-r bg-white md:flex md:flex-col">
        <div className="border-b px-4 py-4">
          <p className="font-semibold text-zinc-900">Présence</p>
          <p className="text-xs text-zinc-500">Administration</p>
        </div>
        <AdminNav />
      </aside>
      <div className="flex flex-1 flex-col">
        <header className="flex items-center justify-between border-b bg-white px-4 py-3">
          <span className="text-sm font-medium text-zinc-700">{user.name}</span>
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
        <main className="flex flex-1 flex-col gap-4 p-6">{children}</main>
      </div>
    </div>
  );
}
