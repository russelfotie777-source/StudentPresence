"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  School,
  CalendarRange,
  BadgeCheck,
  MessageSquareWarning,
  Wallet,
  History,
} from "lucide-react";
import { cn } from "@/lib/utils";

const SECTIONS = [
  { href: "/dashboard", label: "Vue d'ensemble", icon: LayoutDashboard },
  { href: "/catalogue", label: "Catalogue", icon: School },
  { href: "/emplois-du-temps", label: "Emplois du temps", icon: CalendarRange },
  { href: "/validations", label: "Validations", icon: BadgeCheck },
  { href: "/requetes", label: "Requêtes enseignants", icon: MessageSquareWarning },
  { href: "/tarifs", label: "Tarifs horaires", icon: Wallet },
  { href: "/historique", label: "Historique des séances", icon: History },
];

export function AdminNav() {
  const pathname = usePathname();

  return (
    <nav className="flex flex-col gap-0.5 p-3">
      {SECTIONS.map((s) => {
        const active = pathname.startsWith(s.href);
        const Icon = s.icon;
        return (
          <Link
            key={s.href}
            href={s.href}
            className={cn(
              "flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              active
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
            )}
          >
            <Icon className="h-[18px] w-[18px]" strokeWidth={active ? 2.3 : 1.9} />
            {s.label}
          </Link>
        );
      })}
    </nav>
  );
}
