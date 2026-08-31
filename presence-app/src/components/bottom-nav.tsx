"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, History, Wallet, MessageSquareWarning, UserPlus, User } from "lucide-react";
import { cn } from "@/lib/utils";
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

const TEACHER_ITEMS: NavItem[] = [
  { href: "/salaire", label: "Salaire", icon: Wallet },
  { href: "/requetes", label: "Requêtes", icon: MessageSquareWarning },
  { href: "/promotion", label: "Promotion", icon: UserPlus },
];

const PROFILE_ITEM: NavItem = { href: "/profil", label: "Profil", icon: User };

export function BottomNav({ role }: { role: UserRole }) {
  const pathname = usePathname();
  const items = [
    ...BASE_ITEMS,
    ...(role === "Enseignant" ? TEACHER_ITEMS : []),
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
              <Icon
                className={cn(
                  "h-[21px] w-[21px] transition-colors",
                  active ? "text-indigo-600" : "text-ink-300",
                )}
                strokeWidth={active ? 2.3 : 1.9}
              />
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
