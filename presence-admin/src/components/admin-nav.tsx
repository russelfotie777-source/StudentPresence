"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { motion } from "motion/react";
import {
  LayoutDashboard,
  School,
  CalendarRange,
  BadgeCheck,
  MessageSquareWarning,
  ArrowLeftRight,
  Wallet,
  History,
  Users,
  ScanFace,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface NavItem {
  href: string;
  label: string;
  icon: typeof LayoutDashboard;
}

interface NavSection {
  titre?: string;
  items: NavItem[];
}

// Regroupé par ce qu'un admin vient réellement faire, plutôt qu'une liste
// plate de 8 entrées à parcourir une par une pour trouver la bonne.
const SECTIONS: NavSection[] = [
  { items: [{ href: "/dashboard", label: "Vue d'ensemble", icon: LayoutDashboard }] },
  {
    titre: "Pédagogie",
    items: [
      { href: "/catalogue", label: "Catalogue", icon: School },
      { href: "/emplois-du-temps", label: "Emplois du temps", icon: CalendarRange },
    ],
  },
  {
    titre: "Suivi",
    items: [
      { href: "/etudiants", label: "Étudiants", icon: Users },
      { href: "/validations", label: "Validations", icon: BadgeCheck },
      { href: "/requetes", label: "Requêtes enseignants", icon: MessageSquareWarning },
      { href: "/demandes-formation", label: "Migrations FA → FI", icon: ArrowLeftRight },
      { href: "/historique", label: "Historique des séances", icon: History },
    ],
  },
  {
    titre: "Finances & sécurité",
    items: [
      { href: "/tarifs", label: "Tarifs horaires", icon: Wallet },
      { href: "/reconnaissance-faciale", label: "Reconnaissance faciale", icon: ScanFace },
    ],
  },
];

export function AdminNav({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();

  return (
    <nav className="flex flex-col gap-5 px-3">
      {SECTIONS.map((section, i) => (
        <div key={section.titre ?? i} className="flex flex-col gap-0.5">
          {section.titre && (
            <p className="px-3 pb-1.5 text-[11px] font-semibold tracking-wide text-sidebar-foreground/45 uppercase">
              {section.titre}
            </p>
          )}
          {section.items.map((item) => {
            const active = pathname === item.href || pathname.startsWith(item.href + "/");
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={onNavigate}
                className={cn(
                  "relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors",
                  active
                    ? "text-sidebar-primary-foreground"
                    : "text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground",
                )}
              >
                {active && (
                  <motion.span
                    layoutId="admin-nav-active"
                    transition={{ type: "spring", stiffness: 500, damping: 40 }}
                    className="absolute inset-0 rounded-lg bg-primary shadow-sm"
                  />
                )}
                <Icon className="relative z-10 h-[17px] w-[17px] shrink-0" strokeWidth={active ? 2.3 : 1.9} />
                <span className="relative z-10 truncate">{item.label}</span>
              </Link>
            );
          })}
        </div>
      ))}
    </nav>
  );
}
