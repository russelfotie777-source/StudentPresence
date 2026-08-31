"use client";

import { useRouter } from "next/navigation";
import { LogOut, Phone, School, GraduationCap, BookOpen } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { RoleBadge } from "@/components/user-badge";
import { AttendanceTrendChart } from "@/components/attendance-trend-chart";
import { useLogout, useMe } from "@/hooks/use-auth";
import { useAttendanceTrend } from "@/hooks/use-attendance-stats";

export default function ProfilPage() {
  const router = useRouter();
  const { data } = useMe();
  const logout = useLogout();
  const user = data?.user;
  const isEtudiant = user?.effective_role === "Etudiant";
  const { data: trend, isLoading: trendLoading } = useAttendanceTrend(isEtudiant);

  if (!user) return null;

  const initials = user.name
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col items-center gap-3 pt-4 pb-2 text-center">
        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-primary text-2xl font-semibold text-primary-foreground shadow-sm">
          {initials}
        </div>
        <div>
          <h1 className="text-xl font-semibold text-foreground">{user.name}</h1>
          <div className="mt-1 flex justify-center">
            <RoleBadge role={user.effective_role} />
          </div>
        </div>
      </div>

      <div className="overflow-hidden rounded-2xl border border-border bg-card">
        <InfoRow icon={Phone} label="Téléphone" value={user.phone} />
        {user.salle && <InfoRow icon={School} label="Salle" value={user.salle.nom} />}
        {user.filiere && <InfoRow icon={BookOpen} label="Filière" value={user.filiere.nom} />}
        {user.niveau && <InfoRow icon={GraduationCap} label="Niveau" value={user.niveau.nom} />}
      </div>

      {isEtudiant && (trendLoading ? (
        <Skeleton className="h-[248px] w-full rounded-2xl" />
      ) : (
        <AttendanceTrendChart data={trend ?? []} />
      ))}

      {user.has_active_promotion && (
        <div className="rounded-2xl border border-accent bg-accent/60 px-4 py-3 text-sm text-accent-foreground">
          Vous avez actuellement les droits de délégué (promotion temporaire active).
        </div>
      )}

      <Button
        variant="outline"
        className="mt-2 gap-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
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

function InfoRow({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Phone;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center gap-3 border-b border-border px-4 py-3.5 last:border-b-0">
      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted">
        <Icon className="h-4 w-4 text-muted-foreground" />
      </div>
      <div className="flex min-w-0 flex-1 items-center justify-between gap-3">
        <span className="shrink-0 text-sm text-muted-foreground">{label}</span>
        <span className="truncate text-sm font-medium text-foreground">{value}</span>
      </div>
    </div>
  );
}
