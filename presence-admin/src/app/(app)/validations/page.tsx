"use client";

import { useState } from "react";
import { motion } from "motion/react";
import { Check, X, GraduationCap, Users, Inbox } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useApproveUser, usePendingUsers, useRejectUser } from "@/hooks/use-validations";
import type { User } from "@/types/api";

export default function ValidationsPage() {
  const delegues = usePendingUsers("Delegue");
  const enseignants = usePendingUsers("Enseignant");
  // Le refus renvoie le compte à l'état initial : c'est réversible pour
  // l'utilisateur (il peut redemander) mais invisible pour lui, donc une
  // confirmation évite le clic malencontreux sur une ligne voisine.
  const [aRefuser, setARefuser] = useState<User | null>(null);

  const approuver = useApproveUser();
  const refuser = useRejectUser();

  const total = (delegues.data?.length ?? 0) + (enseignants.data?.length ?? 0);
  const enCours = delegues.isLoading || enseignants.isLoading;

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Validations
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {enCours
            ? "Chargement des demandes…"
            : total === 0
              ? "Aucun compte n'attend de validation."
              : `${total} ${total > 1 ? "comptes attendent" : "compte attend"} votre décision.`}
        </p>
      </div>

      <Section
        titre="Délégués"
        icon={Users}
        users={delegues.data}
        loading={delegues.isLoading}
        onApprouver={(u) => approuver.mutate(u)}
        onRefuser={setARefuser}
        idEnCours={approuver.isPending ? approuver.variables?.id : undefined}
      />

      <Section
        titre="Enseignants"
        icon={GraduationCap}
        users={enseignants.data}
        loading={enseignants.isLoading}
        onApprouver={(u) => approuver.mutate(u)}
        onRefuser={setARefuser}
        idEnCours={approuver.isPending ? approuver.variables?.id : undefined}
      />

      <Dialog open={aRefuser !== null} onOpenChange={(ouvert) => !ouvert && setARefuser(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Refuser cette demande ?</DialogTitle>
            <DialogDescription>
              {aRefuser?.name} ne pourra pas se connecter. Le compte revient à son état
              initial : la personne pourra soumettre une nouvelle demande, mais ne sera pas
              prévenue de ce refus.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setARefuser(null)}>
              Annuler
            </Button>
            <Button
              variant="destructive"
              disabled={refuser.isPending}
              onClick={() => {
                if (aRefuser) {
                  refuser.mutate(aRefuser, { onSettled: () => setARefuser(null) });
                }
              }}
            >
              {refuser.isPending ? "Refus…" : "Refuser la demande"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function Section({
  titre,
  icon: Icon,
  users,
  loading,
  onApprouver,
  onRefuser,
  idEnCours,
}: {
  titre: string;
  icon: typeof Users;
  users?: User[];
  loading: boolean;
  onApprouver: (u: User) => void;
  onRefuser: (u: User) => void;
  idEnCours?: number;
}) {
  return (
    <section className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <Icon className="size-4 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">{titre}</h2>
        {users && users.length > 0 && <Badge variant="secondary">{users.length}</Badge>}
      </div>

      {loading && (
        <div className="flex flex-col gap-2">
          {[...Array(2)].map((_, i) => (
            <Skeleton key={i} className="h-[76px] rounded-2xl" />
          ))}
        </div>
      )}

      {users && users.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-10 text-center">
          <Inbox className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">Rien en attente ici.</p>
        </div>
      )}

      <motion.div
        initial="hidden"
        animate="show"
        variants={{ show: { transition: { staggerChildren: 0.05 } } }}
        className="flex flex-col gap-2"
      >
        {users?.map((u) => (
          <LigneCompte
            key={u.id}
            user={u}
            onApprouver={onApprouver}
            onRefuser={onRefuser}
            enCours={idEnCours === u.id}
          />
        ))}
      </motion.div>
    </section>
  );
}

/**
 * Une seule mise en page pour toutes les tailles d'écran, en flux plutôt
 * qu'en tableau : un tableau à quatre colonnes déborde horizontalement sur
 * téléphone, et l'admin doit pouvoir valider un compte depuis le sien.
 */
function LigneCompte({
  user,
  onApprouver,
  onRefuser,
  enCours,
}: {
  user: User;
  onApprouver: (u: User) => void;
  onRefuser: (u: User) => void;
  enCours: boolean;
}) {
  const rattachement = [user.salle?.nom, user.filiere?.nom, user.niveau?.nom].filter(Boolean);

  return (
    <motion.div
      variants={{
        hidden: { opacity: 0, y: 8 },
        show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] } },
      }}
      className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between"
    >
      <div className="flex min-w-0 items-center gap-3">
        <Avatar className="size-9 shrink-0">
          <AvatarFallback>{initiales(user.name)}</AvatarFallback>
        </Avatar>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-foreground">{user.name}</p>
          <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
            <span className="tabular-nums">{user.phone}</span>
            {rattachement.length > 0 && (
              <>
                <span aria-hidden>·</span>
                <span className="truncate">{rattachement.join(" · ")}</span>
              </>
            )}
            {user.formation && (
              <Badge variant="outline" className="h-4 px-1.5 text-[10px]">
                {user.formation}
              </Badge>
            )}
          </div>
        </div>
      </div>

      <div className="flex shrink-0 gap-2">
        <Button
          size="sm"
          className="flex-1 gap-1.5 sm:flex-none"
          disabled={enCours}
          onClick={() => onApprouver(user)}
        >
          <Check className="size-3.5" />
          {enCours ? "…" : "Valider"}
        </Button>
        <Button
          size="sm"
          variant="outline"
          className="flex-1 gap-1.5 sm:flex-none"
          disabled={enCours}
          onClick={() => onRefuser(user)}
        >
          <X className="size-3.5" />
          Refuser
        </Button>
      </div>
    </motion.div>
  );
}

function initiales(nom: string): string {
  return nom
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}
