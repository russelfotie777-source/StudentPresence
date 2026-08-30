"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

const SECTIONS = [
  { href: "/dashboard", label: "Vue d'ensemble", icon: "📊" },
  { href: "/catalogue", label: "Catalogue", icon: "🏫" },
  { href: "/emplois-du-temps", label: "Emplois du temps", icon: "🗓️" },
  { href: "/validations", label: "Validations", icon: "✅" },
  { href: "/requetes", label: "Requêtes enseignants", icon: "📨" },
  { href: "/tarifs", label: "Tarifs horaires", icon: "💰" },
  { href: "/historique", label: "Historique des séances", icon: "🕓" },
];

export function AdminNav() {
  const pathname = usePathname();

  return (
    <nav className="flex flex-col gap-1 p-3">
      {SECTIONS.map((s) => {
        const active = pathname.startsWith(s.href);
        return (
          <Link
            key={s.href}
            href={s.href}
            className={cn(
              "flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors",
              active
                ? "bg-violet-600 text-white"
                : "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900",
            )}
          >
            <span>{s.icon}</span>
            {s.label}
          </Link>
        );
      })}
    </nav>
  );
}
