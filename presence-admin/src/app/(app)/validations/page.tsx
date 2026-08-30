"use client";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useApproveUser, usePendingUsers, useRejectUser } from "@/hooks/use-validations";

export default function ValidationsPage() {
  const { data: delegues, isLoading: loadingDelegues } = usePendingUsers("Delegue");
  const { data: enseignants, isLoading: loadingEnseignants } = usePendingUsers("Enseignant");

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold text-zinc-900">Validations en attente</h1>

      <Section title="Délégués" users={delegues} loading={loadingDelegues} />
      <Section title="Enseignants" users={enseignants} loading={loadingEnseignants} />
    </div>
  );
}

function Section({
  title,
  users,
  loading,
}: {
  title: string;
  users?: { id: number; name: string; phone: string; salle: { nom: string } | null }[];
  loading: boolean;
}) {
  const approve = useApproveUser();
  const reject = useRejectUser();

  return (
    <Card>
      <CardContent className="py-4">
        <h2 className="mb-3 text-sm font-semibold text-zinc-700">{title}</h2>
        {loading && <p className="text-sm text-zinc-500">Chargement…</p>}
        {users && users.length === 0 && (
          <p className="text-sm text-zinc-400">Rien en attente.</p>
        )}
        {users && users.length > 0 && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nom</TableHead>
                <TableHead>Téléphone</TableHead>
                <TableHead>Salle</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.map((u) => (
                <TableRow key={u.id}>
                  <TableCell>{u.name}</TableCell>
                  <TableCell>{u.phone}</TableCell>
                  <TableCell>{u.salle?.nom ?? "—"}</TableCell>
                  <TableCell className="flex justify-end gap-2">
                    <Button size="sm" onClick={() => approve.mutate(u.id)} disabled={approve.isPending}>
                      Valider
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => reject.mutate(u.id)}
                      disabled={reject.isPending}
                    >
                      Refuser
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}
