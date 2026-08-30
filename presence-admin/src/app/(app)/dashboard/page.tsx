"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { apiFetch } from "@/lib/api-client";
import type { RequeteEnseignant, User } from "@/types/api";

export default function DashboardPage() {
  const { data: pendingUsers } = useQuery({
    queryKey: ["validations", "pending"],
    queryFn: () => apiFetch<User[]>("/api/validations?statut=pending"),
  });

  const { data: pendingRequetes } = useQuery({
    queryKey: ["requetes", "en_attente"],
    queryFn: () => apiFetch<RequeteEnseignant[]>("/api/requetes?statut=en_attente"),
  });

  const delegues = pendingUsers?.filter((u) => u.role === "Delegue").length ?? 0;
  const enseignants = pendingUsers?.filter((u) => u.role === "Enseignant").length ?? 0;

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold text-zinc-900">Vue d&apos;ensemble</h1>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label="Délégués en attente"
          value={delegues}
          href="/validations?role=Delegue"
        />
        <StatCard
          label="Enseignants en attente"
          value={enseignants}
          href="/validations?role=Enseignant"
        />
        <StatCard
          label="Requêtes en attente"
          value={pendingRequetes?.length ?? 0}
          href="/requetes"
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Accès rapides</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-3">
          <QuickLink href="/catalogue" label="Gérer le catalogue" />
          <QuickLink href="/emplois-du-temps" label="Générer un emploi du temps" />
          <QuickLink href="/tarifs" label="Tarifs horaires" />
          <QuickLink href="/historique" label="Historique des séances" />
        </CardContent>
      </Card>
    </div>
  );
}

function StatCard({ label, value, href }: { label: string; value: number; href: string }) {
  return (
    <Link href={href}>
      <Card className="transition-colors hover:border-violet-400">
        <CardContent className="py-5">
          <p className="text-3xl font-bold text-violet-700">{value}</p>
          <p className="text-sm text-zinc-500">{label}</p>
        </CardContent>
      </Card>
    </Link>
  );
}

function QuickLink({ href, label }: { href: string; label: string }) {
  return (
    <Link
      href={href}
      className="rounded-md border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-medium text-violet-700 hover:bg-violet-100"
    >
      {label}
    </Link>
  );
}
