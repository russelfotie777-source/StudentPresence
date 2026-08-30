"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { BottomNav } from "@/components/bottom-nav";
import { useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { data, isLoading, isError } = useMe();
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
      <div className="flex min-h-screen items-center justify-center bg-background">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="mx-auto flex min-h-screen w-full max-w-lg flex-col bg-background">
      <main className="flex flex-1 flex-col px-4 pt-6 pb-24">{children}</main>
      <BottomNav role={user.effective_role} />
    </div>
  );
}
