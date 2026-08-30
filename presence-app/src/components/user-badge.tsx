import { Badge } from "@/components/ui/badge";
import type { UserRole } from "@/types/api";

const ROLE_LABELS: Record<UserRole, string> = {
  Etudiant: "Étudiant",
  Delegue: "Délégué",
  Enseignant: "Enseignant",
  Admin: "Admin",
};

export function RoleBadge({ role }: { role: UserRole }) {
  return <Badge variant="secondary">{ROLE_LABELS[role]}</Badge>;
}
