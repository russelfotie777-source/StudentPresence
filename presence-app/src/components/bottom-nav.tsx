"use client";

import Link from "next/link";
import { ZirisMark, ZirisWordmark } from "@/components/ziris-brand";
import { usePathname } from "next/navigation";
import {
  ArrowLeftRight,
  Home,
  History,
  Wallet,
  MessageSquareWarning,
  UserPlus,
  User,
} from "lucide-react";
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
const PROMOTION_ITEM: NavItem = {
  href: "/promotion",
  label: "Promotion",
  icon: UserPlus,
};

// Un étudiant en alternance peut demander à suivre les cours de jour ; un
// délégué non, il porte sa salle. L'onglet existe pour tous les étudiants,
// l'écran explique à chacun où il en est.
const MIGRATION_ITEM: NavItem = {
  href: "/migration",
  label: "Migration",
  icon: ArrowLeftRight,
};

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
    ...(role === "Etudiant" ? [MIGRATION_ITEM] : []),
    PROFILE_ITEM,
  ];

  return (
    <nav className="app-nav" aria-label="Navigation principale">
      <Link href="/dashboard" className="nav-brand presence-brand">
        <span className="brand-mark">
          <ZirisMark size={22} />
        </span>
        <ZirisWordmark />
      </Link>
      <div
        className="nav-items"
        style={{ "--nav-count": items.length } as React.CSSProperties}
      >
        {items.map((item) => {
          const active =
            pathname === item.href || pathname.startsWith(item.href + "/");
          const Icon = item.icon;
          return (
            <Link
              key={item.href}
              href={item.href}
              aria-current={active ? "page" : undefined}
              title={item.label}
              className={cn("nav-item", active && "nav-item-active")}
            >
              <span className="relative">
                <Icon
                  className={cn(
                    "h-[21px] w-[21px] transition-colors",
                    active ? "text-primary" : "text-muted-foreground",
                  )}
                  strokeWidth={active ? 2.3 : 1.9}
                />
                {item.href === PROFILE_ITEM.href && nonLues > 0 && (
                  <span
                    className="absolute -top-1 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-xs font-bold text-white tabular-nums ring-2 ring-card"
                    aria-label={`${nonLues} notification(s) non lue(s)`}
                  >
                    {nonLues > 9 ? "9+" : nonLues}
                  </span>
                )}
              </span>
              <span className="nav-label">{item.label}</span>
            </Link>
          );
        })}
      </div>
      <div className="nav-footnote">
        <span className="metric-marker" /> IUT de Douala
      </div>
    </nav>
  );
}
