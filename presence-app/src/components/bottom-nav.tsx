"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, Wallet, MessageSquareWarning, UserPlus, User } from "lucide-react";
import { cn } from "@/lib/utils";
import type { UserRole } from "@/types/api";

interface NavItem {
  href: string;
  label: string;
  icon: typeof Home;
}

const BASE_ITEMS: NavItem[] = [{ href: "/dashboard", label: "Accueil", icon: Home }];

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
      className="fixed inset-x-0 bottom-0 z-40 border-t border-border/70 bg-card/95 backdrop-blur-lg [padding-bottom:env(safe-area-inset-bottom)]"
      aria-label="Navigation principale"
    >
      <div
        className="mx-auto grid max-w-lg"
        style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }}
      >
        {items.map((item) => {
          const active = pathname === item.href || pathname.startsWith(item.href + "/");
          const Icon = item.icon;
          return (
            <Link
              key={item.href}
              href={item.href}
              className="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium transition-colors"
            >
              <Icon
                className={cn(
                  "h-[22px] w-[22px] transition-colors",
                  active ? "text-primary" : "text-muted-foreground",
                )}
                strokeWidth={active ? 2.4 : 1.9}
              />
              <span className={active ? "text-primary" : "text-muted-foreground"}>
                {item.label}
              </span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
