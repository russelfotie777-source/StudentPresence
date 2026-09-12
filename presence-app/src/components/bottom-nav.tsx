"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, History, Wallet, MessageSquareWarning, UserPlus, User } from "lucide-react";
import { cn } from "@/lib/utils";
import { useNotifications } from "@/hooks/use-notifications";
import type { UserRole } from "@/types/api";

interface NavItem {
  href: string;
  label: string;
  icon: typeof Home;
}

// Historique visible partout : le délégué reste un étudiant (juste désigné
// avec des droits en plus) et un enseignant consulte aussi ses séances
// passées, donc l'onglet vit dans le socle commun à tous les rôles.
const BASE_ITEMS: NavItem[] = [
  { href: "/dashboard", label: "Accueil", icon: Home },
  { href: "/historique", label: "Historique", icon: History },
];

const TEACHER_ONLY_ITEMS: NavItem[] = [
  { href: "/salaire", label: "Salaire", icon: Wallet },
  { href: "/requetes", label: "Requêtes", icon: MessageSquareWarning },
];

// Un délégué (titulaire, ou étudiant actuellement promu via effective_role)
// peut lui aussi désigner un remplaçant temporaire — voir PromotionController.
const PROMOTION_ITEM: NavItem = { href: "/promotion", label: "Promotion", icon: UserPlus };

const PROFILE_ITEM: NavItem = { href: "/profil", label: "Profil", icon: User };

export function BottomNav({ role }: { role: UserRole }) {
  const pathname = usePathname();
  // Pastille sur Profil : c'est là que vivent les notifications, et une
  // restriction de compte doit être vue même sans ouvrir cet écran.
  const { data: notifications } = useNotifications();
  const nonLues = notifications?.non_lues ?? 0;
  const items = [
    ...BASE_ITEMS,
    ...(role === "Enseignant" ? TEACHER_ONLY_ITEMS : []),
    ...(role === "Enseignant" || role === "Delegue" ? [PROMOTION_ITEM] : []),
    PROFILE_ITEM,
  ];

  return (
    <nav
      className="fixed inset-x-4 bottom-5 z-40 mx-auto max-w-lg [margin-bottom:env(safe-area-inset-bottom)]"
      aria-label="Navigation principale"
    >
      <div
        className="grid rounded-[28px] border border-line/90 bg-card/85 p-2.5 shadow-[0_16px_32px_-14px_rgba(20,18,31,.22),inset_0_1px_0_rgba(255,255,255,.9)] backdrop-blur-xl"
        style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }}
      >
        {items.map((item) => {
          const active = pathname === item.href || pathname.startsWith(item.href + "/");
          const Icon = item.icon;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "flex flex-col items-center gap-1 rounded-2xl py-2 text-[10.5px] font-medium transition-colors",
                active && "bg-indigo-50",
              )}
            >
              <span className="relative">
                <Icon
                  className={cn(
                    "h-[21px] w-[21px] transition-colors",
                    active ? "text-indigo-600" : "text-ink-300",
                  )}
                  strokeWidth={active ? 2.3 : 1.9}
                />
                {item.href === PROFILE_ITEM.href && nonLues > 0 && (
                  <span
                    className="absolute -top-1 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[9.5px] font-bold text-white tabular-nums ring-2 ring-card"
                    aria-label={`${nonLues} notification(s) non lue(s)`}
                  >
                    {nonLues > 9 ? "9+" : nonLues}
                  </span>
                )}
              </span>
              <span className={active ? "font-bold text-indigo-600" : "text-ink-300"}>
                {item.label}
              </span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
