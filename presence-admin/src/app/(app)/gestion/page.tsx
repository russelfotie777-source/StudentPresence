"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { motion } from "motion/react";
import {
  ArrowLeftRight,
  BadgeCheck,
  Calculator,
  FileDown,
  FileSpreadsheet,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { semaineHooks } from "@/hooks/use-catalog";
import {
  useComptabilite,
  useMigrants,
  usePresenceAutomatique,
  usePrivilegies,
  useTelechargerComptabilite,
  useTelechargerListeMigrants,
  type Comptabilite,
  type MigrantEtudiant,
  type Privilegie,
} from "@/hooks/use-gestion";
import { useHeureDouala } from "@/hooks/use-heure";
import { RechercheGlobale } from "@/components/etudiants/recherche-globale";
import { DialoguePresenceAuto } from "@/components/etudiants/dialogues";
import { semaineCouvrant } from "@/components/navigateur-semaine";
import { plageSemaine } from "@/lib/dates";
import { cn } from "@/lib/utils";
import type { User } from "@/types/api";

type Onglet = "migrants" | "comptabilite" | "privileges";

export default function GestionPage() {
  const [onglet, setOnglet] = useState<Onglet>("migrants");

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Gestion des étudiants
        </h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Les étudiants migrants et leurs listes par salle d&apos;accueil, la comptabilité
          générale de l&apos;application, et le privilège « toujours présent ».
        </p>
      </div>

      <Tabs value={onglet} onValueChange={(v) => setOnglet(v as Onglet)}>
        <TabsList>
          <TabsTrigger value="migrants">Migrants FM</TabsTrigger>
          <TabsTrigger value="comptabilite">Comptabilité</TabsTrigger>
          <TabsTrigger value="privileges">Toujours présents</TabsTrigger>
        </TabsList>
      </Tabs>

      {onglet === "migrants" && <PanneauMigrants />}
      {onglet === "comptabilite" && <PanneauComptabilite />}
      {onglet === "privileges" && <PanneauPrivileges />}
    </div>
  );
}

// --- Migrants FM ---------------------------------------------------------------

function PanneauMigrants() {
  const { data, isLoading } = useMigrants();
  const { data: semaines } = semaineHooks.useList();
  const heure = useHeureDouala();
  const telecharger = useTelechargerListeMigrants();
  const semainesTriees = useMemo(() => [...(semaines ?? [])].sort((a, b) => a.numero - b.numero), [semaines]);
  const semaineDuJour = semaineCouvrant(semainesTriees, heure.date);
  const [semaineId, setSemaineId] = useState<string>("");
  const semaineChoisie = semaineId || (semaineDuJour ? String(semaineDuJour.id) : "");

  if (isLoading || !data) {
    return <Skeleton className="h-64 w-full rounded-2xl" />;
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-xs">
        <p className="text-sm text-muted-foreground">
          <span className="font-semibold text-foreground tabular-nums">{data.total}</span> étudiant
          {data.total > 1 ? "s" : ""} migrant{data.total > 1 ? "s" : ""} (FM), accueilli
          {data.total > 1 ? "s" : ""} dans {data.salles.length} salle{data.salles.length > 1 ? "s" : ""} de jour.
          {data.demandes_en_attente > 0 && (
            <>
              {" "}
              <Link href="/demandes-formation" className="font-medium text-primary hover:underline">
                {data.demandes_en_attente} demande{data.demandes_en_attente > 1 ? "s" : ""} en attente
              </Link>
              .
            </>
          )}
        </p>
        <div className="flex items-center gap-2">
          <span className="text-xs text-muted-foreground">Semaine des listes</span>
          <Select value={semaineChoisie} onValueChange={(v) => setSemaineId(v ?? "")}>
            <SelectTrigger className="h-9 w-56 rounded-lg">
              <SelectValue placeholder="Choisir une semaine…">
                {() => {
                  const s = semainesTriees.find((s) => String(s.id) === semaineChoisie);
                  return s ? `S${s.numero} · ${plageSemaine(s.date_debut, s.date_fin)}` : "Choisir une semaine…";
                }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {semainesTriees.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  S{s.numero} · {plageSemaine(s.date_debut, s.date_fin)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {data.salles.length === 0 && (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-12 text-center">
          <ArrowLeftRight className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">Aucun étudiant migrant pour l&apos;instant.</p>
        </div>
      )}

      {data.salles.map((groupe) => (
        <motion.section
          key={groupe.salle?.id ?? "sans-salle"}
          initial={{ opacity: 0, y: 6 }}
          animate={{ opacity: 1, y: 0 }}
          className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
        >
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
            <div className="min-w-0">
              <p className="text-sm font-semibold text-foreground">
                {groupe.salle?.nom ?? "Sans salle"}
                <span className="ml-2 font-normal text-muted-foreground">
                  {[groupe.salle?.departement, groupe.salle?.filiere, groupe.salle?.niveau].filter(Boolean).join(" · ")}
                </span>
              </p>
              <p className="text-xs text-muted-foreground">
                {groupe.etudiants.length} migrant{groupe.etudiants.length > 1 ? "s" : ""} accueilli
                {groupe.etudiants.length > 1 ? "s" : ""}
              </p>
            </div>
            {groupe.salle && (
              <div className="flex items-center gap-1.5">
                <Button
                  variant="outline"
                  size="sm"
                  className="gap-1.5"
                  disabled={!semaineChoisie || telecharger.isPending}
                  onClick={() =>
                    telecharger.mutate({ salleId: groupe.salle!.id, semaineId: Number(semaineChoisie), format: "pdf" })
                  }
                >
                  <FileDown className="size-3.5" />
                  Liste des migrants
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="gap-1.5"
                  disabled={!semaineChoisie || telecharger.isPending}
                  onClick={() =>
                    telecharger.mutate({ salleId: groupe.salle!.id, semaineId: Number(semaineChoisie), format: "xlsx" })
                  }
                >
                  <FileSpreadsheet className="size-3.5" />
                  Excel
                </Button>
              </div>
            )}
          </div>
          <ul className="divide-y divide-border">
            {groupe.etudiants.map((e) => (
              <LigneMigrant key={e.id} etudiant={e} />
            ))}
          </ul>
        </motion.section>
      ))}
    </div>
  );
}

function LigneMigrant({ etudiant: e }: { etudiant: MigrantEtudiant }) {
  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-3">
      <Avatar className="size-8 shrink-0">
        <AvatarFallback>{initiales(e.name)}</AvatarFallback>
      </Avatar>
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-1.5 truncate text-sm font-medium text-foreground">
          {e.name}
          {e.presence_automatique && <BadgeCheck className="size-3.5 text-primary" aria-label="Toujours présent" />}
        </p>
        <p className="truncate text-xs text-muted-foreground tabular-nums">
          {e.phone}
          {e.salle_origine && <> · venu de {e.salle_origine}</>}
          {e.migre_le && <> · depuis le {dateCourte(e.migre_le)}</>}
        </p>
      </div>
      {e.statut_compte !== "actif" && (
        <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
          {e.statut_compte === "bloque" ? "Bloqué" : "Restreint"}
        </span>
      )}
      <span className="shrink-0 text-right text-xs text-muted-foreground tabular-nums">
        {e.taux === null ? (
          "Pas encore appelé"
        ) : (
          <>
            <span className={cn("font-semibold", e.taux >= 75 ? "text-success" : e.taux >= 50 ? "text-warning-foreground" : "text-destructive")}>
              {e.taux} %
            </span>{" "}
            · {e.presents}/{e.appels} appels
          </>
        )}
      </span>
    </li>
  );
}

// --- Comptabilité ---------------------------------------------------------------

function PanneauComptabilite() {
  const [du, setDu] = useState("");
  const [au, setAu] = useState("");
  const { data, isLoading } = useComptabilite(du || undefined, au || undefined);
  const exporter = useTelechargerComptabilite();

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-xs">
        <Champ label="Du">
          <Input type="date" value={du} onChange={(e) => setDu(e.target.value)} className="h-9 w-40 rounded-lg" />
        </Champ>
        <Champ label="Au">
          <Input type="date" value={au} onChange={(e) => setAu(e.target.value)} className="h-9 w-40 rounded-lg" />
        </Champ>
        {(du || au) && (
          <Button variant="ghost" size="sm" onClick={() => { setDu(""); setAu(""); }}>
            Année académique
          </Button>
        )}
        {data && (
          <p className="ml-auto text-xs text-muted-foreground">
            Période du {dateCourte(data.periode.du)} au {dateCourte(data.periode.au)}
          </p>
        )}
        <Button
          variant="outline"
          size="sm"
          className="gap-1.5"
          disabled={!data || exporter.isPending}
          onClick={() => exporter.mutate({ du: du || undefined, au: au || undefined })}
        >
          <FileSpreadsheet className="size-3.5" />
          {exporter.isPending ? "Génération…" : "Exporter en Excel"}
        </Button>
      </div>

      {isLoading || !data ? (
        <Skeleton className="h-96 w-full rounded-2xl" />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Tuile label="Étudiants" valeur={data.effectifs.etudiants} detail={`${data.effectifs.par_formation.FI} FI · ${data.effectifs.par_formation.FA} FA · ${data.effectifs.par_formation.FM} FM`} />
            <Tuile label="Enseignants validés" valeur={data.effectifs.enseignants} detail={`${data.effectifs.delegues} délégué${data.effectifs.delegues > 1 ? "s" : ""}`} />
            <Tuile label="Séances tenues" valeur={data.seances.tenues} detail={`sur ${data.seances.passees} passées${data.seances.taux_tenue !== null ? ` · ${data.seances.taux_tenue} %` : ""}`} />
            <Tuile label="Heures de cours" valeur={data.seances.heures_effectuees} detail={`${data.seances.a_venir} séance${data.seances.a_venir > 1 ? "s" : ""} à venir`} />
            <Tuile label="Assiduité" valeur={data.assiduite.taux !== null ? `${data.assiduite.taux} %` : "—"} detail={`${data.assiduite.presents} présents · ${data.assiduite.absents} absents`} />
            <Tuile label="Présences forcées" valeur={data.assiduite.forcees_par_admin} detail={`${data.assiduite.automatiques} automatiques`} />
            <Tuile label="Confirmées par le délégué" valeur={data.seances.confirmees_par_delegue} detail="enseignant absent de l'app" />
            <Tuile label="Migrations" valeur={data.migrations.acceptees} detail={`${data.migrations.en_attente} en attente · ${data.migrations.rejetees} rejetée${data.migrations.rejetees > 1 ? "s" : ""}`} />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <TableauPaie paie={data.paie} />
            <TableauDepartements departements={data.effectifs.par_departement} />
          </div>
        </>
      )}
    </div>
  );
}

function TableauPaie({ paie }: { paie: Comptabilite["paie"] }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs lg:col-span-3">
      <div className="flex items-center gap-2 border-b border-border px-4 py-3">
        <Calculator className="size-4 text-muted-foreground" />
        <p className="text-sm font-semibold text-foreground">Paie des enseignants</p>
        <span className="ml-auto text-xs text-muted-foreground">même calcul que leur onglet Salaire</span>
      </div>
      {paie.enseignants.length === 0 ? (
        <p className="px-4 py-6 text-center text-[13px] text-muted-foreground">Aucune séance payable sur la période.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead className="text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Enseignant</th>
                <th className="px-3 py-2 text-right font-medium">Séances</th>
                <th className="px-3 py-2 text-right font-medium">Heures</th>
                <th className="px-3 py-2 text-right font-medium">Pénalités</th>
                <th className="px-4 py-2 text-right font-medium">Salaire</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {paie.enseignants.map((l) => (
                <tr key={l.id}>
                  <td className="px-4 py-2 font-medium text-foreground">{l.name}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{l.seances}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{l.heures} h</td>
                  <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">{fcfa(l.penalites)}</td>
                  <td className="px-4 py-2 text-right font-semibold tabular-nums">{fcfa(l.salaire)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t border-border bg-muted/40 font-semibold">
              <tr>
                <td className="px-4 py-2">Total</td>
                <td className="px-3 py-2 text-right tabular-nums">{paie.enseignants.reduce((t, l) => t + l.seances, 0)}</td>
                <td className="px-3 py-2 text-right tabular-nums">{paie.total_heures} h</td>
                <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">{fcfa(paie.total_penalites)}</td>
                <td className="px-4 py-2 text-right tabular-nums">{fcfa(paie.total_salaire)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </section>
  );
}

function TableauDepartements({ departements }: { departements: Comptabilite["effectifs"]["par_departement"] }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs lg:col-span-2">
      <div className="border-b border-border px-4 py-3">
        <p className="text-sm font-semibold text-foreground">Effectifs par département</p>
      </div>
      <table className="w-full text-[13px]">
        <thead className="text-left text-xs text-muted-foreground">
          <tr>
            <th className="px-4 py-2 font-medium">Département</th>
            <th className="px-3 py-2 text-right font-medium">Salles</th>
            <th className="px-4 py-2 text-right font-medium">Étudiants</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {departements.map((d) => (
            <tr key={d.code}>
              <td className="px-4 py-2">
                <span className="font-medium text-foreground">{d.code}</span>
                <span className="ml-2 text-muted-foreground">{d.nom}</span>
              </td>
              <td className="px-3 py-2 text-right tabular-nums">{d.salles}</td>
              <td className="px-4 py-2 text-right font-semibold tabular-nums">{d.etudiants}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

// --- Toujours présents ------------------------------------------------------------

function PanneauPrivileges() {
  const { data, isLoading } = usePrivilegies();
  const basculer = usePresenceAutomatique();
  const [recherche, setRecherche] = useState("");
  const [candidat, setCandidat] = useState<User | null>(null);
  const [aRetirer, setARetirer] = useState<Privilegie | null>(null);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs">
        <p className="text-sm text-muted-foreground">
          Un étudiant « toujours présent » est compté présent à chaque séance de sa salle, dès
          qu&apos;elle commence, sans pointer et quel que soit l&apos;appel du délégué. Une présence
          que vous posez vous-même sur une séance garde le dernier mot.
        </p>
        <div className="flex items-center gap-2">
          <RechercheGlobale
            valeur={recherche}
            onChange={setRecherche}
            onChoisir={(u) => {
              setRecherche("");
              setCandidat(u);
            }}
            salleAffichee={null}
          />
        </div>
      </div>

      {isLoading ? (
        <Skeleton className="h-40 w-full rounded-2xl" />
      ) : data && data.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border py-12 text-center">
          <BadgeCheck className="size-5 text-muted-foreground/60" />
          <p className="text-[13px] text-muted-foreground">
            Personne n&apos;a ce privilège. Cherchez un étudiant ci-dessus pour l&apos;accorder.
          </p>
        </div>
      ) : (
        <ul className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
          {data?.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
              <Avatar className="size-8 shrink-0">
                <AvatarFallback>{initiales(p.name)}</AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">
                  {p.name}
                  {p.salle && <span className="ml-2 font-normal text-muted-foreground">{p.salle.nom}{p.formation ? ` · ${p.formation}` : ""}</span>}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                  {p.motif ? `« ${p.motif} »` : "Sans motif"}
                  {p.depuis && <> · depuis le {dateCourte(p.depuis)}</>}
                </p>
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="gap-1.5 text-muted-foreground hover:text-destructive"
                disabled={basculer.isPending}
                onClick={() => setARetirer(p)}
              >
                <X className="size-3.5" /> Retirer
              </Button>
            </li>
          ))}
        </ul>
      )}

      {candidat && (
        <DialoguePresenceAuto
          etudiant={candidat}
          enCours={basculer.isPending}
          onConfirmer={(actif, motif) =>
            basculer.mutate({ id: candidat.id, actif, motif }, { onSuccess: () => setCandidat(null) })
          }
          onFermer={() => setCandidat(null)}
        />
      )}
      {aRetirer && (
        <DialoguePresenceAuto
          etudiant={{ ...vide, id: aRetirer.id, name: aRetirer.name, phone: aRetirer.phone, presence_automatique: true }}
          enCours={basculer.isPending}
          onConfirmer={(actif) => basculer.mutate({ id: aRetirer.id, actif }, { onSuccess: () => setARetirer(null) })}
          onFermer={() => setARetirer(null)}
        />
      )}
    </div>
  );
}

/** Un `User` minimal pour le dialogue de retrait, qui ne lit que le nom et le privilège. */
const vide: User = {
  id: 0,
  name: "",
  phone: "",
  role: "Etudiant",
  effective_role: "Etudiant",
  validation_status: "approved",
  statut_compte: "actif",
  motif_statut: null,
  statut_modifie_le: null,
  formation: null,
  salle: null,
  niveau: null,
  filiere: null,
  quota: 0,
  has_active_promotion: false,
};

// --- Petits blocs ------------------------------------------------------------

function Tuile({ label, valeur, detail }: { label: string; valeur: number | string; detail?: string }) {
  return (
    <div className="flex flex-col gap-1 rounded-2xl border border-border bg-card p-4 shadow-xs">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="font-display text-2xl font-semibold leading-none text-foreground tabular-nums">{valeur}</p>
      {detail && <p className="text-xs text-muted-foreground">{detail}</p>}
    </div>
  );
}

function Champ({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="flex flex-col gap-1 text-xs text-muted-foreground">
      {label}
      {children}
    </label>
  );
}

function fcfa(montant: number): string {
  return `${Math.round(montant).toLocaleString("fr-FR")} F`;
}

function dateCourte(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "short", year: "numeric" });
}

function initiales(nom: string): string {
  return nom
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((m) => m[0]?.toUpperCase() ?? "")
    .join("");
}
