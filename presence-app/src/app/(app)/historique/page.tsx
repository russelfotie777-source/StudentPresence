"use client";

import { useState } from "react";
import Link from "next/link";
import { motion } from "motion/react";
import {
  ArrowDown,
  ArrowUpRight,
  Check,
  History,
  LoaderCircle,
  MapPin,
  Minus,
  RefreshCw,
  Search,
  X,
} from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { ThemeToggle } from "@/components/theme-toggle";
import { ZirisMark, ZirisWordmark } from "@/components/ziris-brand";
import { useHistorySeances } from "@/hooks/use-seances";
import { useMe } from "@/hooks/use-auth";
import { lignes, total } from "@/lib/pagination";
import type { Seance, UserRole } from "@/types/api";
import styles from "./historique.module.css";

type Filter = "all" | "present" | "absent";
const filters: { value: Filter; label: string }[] = [
  { value: "all", label: "Tout" },
  { value: "present", label: "Présences" },
  { value: "absent", label: "Absences" },
];

function presence(seance: Seance, role?: UserRole) {
  // Le statut de l'enseignant ne remplace jamais celui de l'étudiant.
  return role === "Etudiant" ? (seance.ma_presence ?? null) : seance.etat_final;
}

function normalize(value: string) {
  return value
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "")
    .toLocaleLowerCase("fr")
    .trim();
}

function groupByDate(seances: Seance[]) {
  const groups = new Map<string | null, Seance[]>();
  for (const seance of seances) {
    const items = groups.get(seance.date_seance) ?? [];
    items.push(seance);
    groups.set(seance.date_seance, items);
  }
  return [...groups].sort(([a], [b]) => (b ?? "").localeCompare(a ?? ""));
}

export default function HistoriquePage() {
  const {
    data,
    isLoading,
    isError,
    isFetchNextPageError,
    refetch,
    isFetching,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useHistorySeances();
  const { data: me } = useMe();
  const [filter, setFilter] = useState<Filter>("all");
  const [search, setSearch] = useState("");
  const role = me?.user.effective_role;
  const seances = [
    ...new Map(
      lignes(data?.pages).map((seance) => [seance.id, seance]),
    ).values(),
  ];
  const query = normalize(search);
  const filtered = seances.filter(
    (seance) =>
      (filter === "all" || presence(seance, role) === filter) &&
      (!query ||
        normalize(
          [seance.matiere, seance.salle, seance.enseignant, seance.groupe]
            .filter(Boolean)
            .join(" "),
        ).includes(query)),
  );
  const groups = groupByDate(filtered);
  const hasFilters = filter !== "all" || !!query;
  const resetFilters = () => {
    setFilter("all");
    setSearch("");
  };

  return (
    <div className={styles.history}>
      <header className={styles.topbar}>
        <Link
          href="/dashboard"
          className={styles.brand}
          aria-label="Ziris, accueil"
        >
          <span>
            <ZirisMark size={22} />
          </span>
          <ZirisWordmark />
        </Link>
        <span className={styles.context}>
          MON CAMPUS <span>/</span> HISTORIQUE
        </span>
        <ThemeToggle />
      </header>

      <div className={styles.heading}>
        <div>
          <p className={styles.eyebrow}>
            <span />
            {role === "Delegue" ? "Présence des enseignants" : "Votre parcours"}
          </p>
          <h1>
            Historique<span>.</span>
          </h1>
          <p className={styles.subtitle}>
            {role === "Etudiant"
              ? "Chaque présence fait la différence."
              : "Le suivi de vos séances passées."}
          </p>
        </div>
        <div className={styles.total}>
          <strong>{isLoading ? "…" : data ? total(data.pages) : "—"}</strong>
          <span>
            séance{total(data?.pages) === 1 ? "" : "s"}
            <br />
            au total
          </span>
        </div>
      </div>

      <div className={styles.toolbar}>
        <div
          className={styles.filters}
          role="group"
          aria-label="Filtrer les présences"
        >
          {filters.map(({ value, label }) => (
            <button
              key={value}
              type="button"
              aria-pressed={filter === value}
              onClick={() => setFilter(value)}
            >
              {value === "present" && <Check size={14} />}
              {value === "absent" && <X size={14} />}
              {label}
            </button>
          ))}
        </div>
        <div className={styles.search} role="search">
          <Search size={17} aria-hidden="true" />
          <input
            type="search"
            aria-label="Rechercher dans les séances chargées"
            placeholder="Matière, enseignant, salle…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          {search && (
            <button
              type="button"
              onClick={() => setSearch("")}
              aria-label="Effacer la recherche"
              title="Effacer la recherche"
            >
              <X size={15} />
            </button>
          )}
        </div>
      </div>

      {!isLoading && data && (
        <p className={styles.resultCount} role="status">
          {hasFilters
            ? `${filtered.length} résultat${filtered.length === 1 ? "" : "s"}`
            : `${seances.length} séance${seances.length === 1 ? "" : "s"}`}
          {hasFilters && hasNextPage
            ? ` parmi ${seances.length} séances chargées sur ${total(data.pages)}`
            : hasNextPage
              ? ` chargées sur ${total(data.pages)}`
              : hasFilters
                ? ` sur ${seances.length} séances`
                : " dans votre historique"}
        </p>
      )}

      {isLoading && (
        <div
          className={styles.loading}
          aria-label="Chargement de l’historique"
          role="status"
        >
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-28 w-full rounded-lg" />
          ))}
        </div>
      )}

      {isError && !isFetchNextPageError && (
        <div className={styles.error} role="alert">
          <div>
            <strong>Historique indisponible</strong>
            <p>
              La connexion n’a pas abouti. Vos données ne sont pas modifiées.
            </p>
          </div>
          <button onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw size={15} /> Réessayer
          </button>
        </div>
      )}

      {!isLoading && !isError && seances.length === 0 && (
        <div className={styles.empty}>
          <History size={32} strokeWidth={1.5} />
          <h2>Votre parcours commence ici.</h2>
          <p>Vos séances passées apparaîtront dans cet espace.</p>
          <Link href="/dashboard">
            Revenir à ma journée <ArrowUpRight size={16} />
          </Link>
        </div>
      )}

      {!isLoading && seances.length > 0 && filtered.length === 0 && (
        <div className={styles.empty}>
          <Search size={30} strokeWidth={1.5} />
          <h2>Aucune séance correspondante.</h2>
          <p>
            {hasNextPage
              ? "Aucun résultat dans les séances déjà chargées."
              : "Aucun résultat pour cette recherche ou ce statut."}
          </p>
          <button onClick={resetFilters}>
            Réinitialiser les filtres <X size={15} />
          </button>
        </div>
      )}

      <div className={styles.timeline}>
        {groups.map(([date, items]) => {
          const day = date ? new Date(`${date}T00:00:00`) : null;
          return (
            <section
              key={date ?? "sans-date"}
              className={styles.day}
              aria-label={
                day?.toLocaleDateString("fr-FR", { dateStyle: "full" }) ??
                "Date inconnue"
              }
            >
              <h2 className={styles.date}>
                <span className={styles.dayNumber}>
                  {day ? day.getDate().toString().padStart(2, "0") : "—"}
                </span>
                <span className={styles.dateWords}>
                  <strong>
                    {day?.toLocaleDateString("fr-FR", { weekday: "long" }) ??
                      "Date inconnue"}
                  </strong>
                  <span>
                    {day?.toLocaleDateString("fr-FR", {
                      month: "long",
                      year: "numeric",
                    })}
                  </span>
                </span>
                <span className={styles.dayCount}>
                  {items.length} séance{items.length === 1 ? "" : "s"}
                </span>
              </h2>
              <ol className={styles.sessions}>
                {[...items]
                  .sort((a, b) => b.heure_debut.localeCompare(a.heure_debut))
                  .map((seance, index) => (
                    <motion.li
                      key={seance.id}
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{
                        duration: 0.25,
                        delay: Math.min(index, 4) * 0.035,
                      }}
                    >
                      <HistoriqueRow seance={seance} role={role} />
                    </motion.li>
                  ))}
              </ol>
            </section>
          );
        })}
      </div>

      {isFetchNextPageError && (
        <p role="alert" className={styles.pageError}>
          La suite n’a pas pu être chargée. Les séances précédentes restent
          disponibles.
        </p>
      )}
      {hasNextPage && (
        <div className={styles.more}>
          <span>
            {seances.length} / {total(data?.pages)} séances chargées
          </span>
          <button onClick={() => fetchNextPage()} disabled={isFetchingNextPage}>
            {isFetchingNextPage ? (
              <LoaderCircle size={16} className={styles.spinner} />
            ) : isFetchNextPageError ? (
              <RefreshCw size={16} />
            ) : (
              <ArrowDown size={16} />
            )}
            {isFetchingNextPage
              ? "Chargement…"
              : isFetchNextPageError
                ? "Réessayer"
                : "Charger la suite"}
          </button>
        </div>
      )}
      {!isLoading && !hasNextPage && filtered.length > 0 && (
        <p className={styles.end}>
          <span />
          Fin de l’historique
          <span />
        </p>
      )}
    </div>
  );
}

function HistoriqueRow({ seance, role }: { seance: Seance; role?: UserRole }) {
  const statut = presence(seance, role);
  const Icon = statut === "present" ? Check : statut === "absent" ? X : Minus;
  const label =
    statut === "present"
      ? "Présent"
      : statut === "absent"
        ? "Absent"
        : "Non renseigné";

  return (
    <article className={styles.row}>
      <div className={styles.time}>
        <time dateTime={seance.heure_debut}>
          {seance.heure_debut.slice(0, 5)}
        </time>
        <span />
        <time dateTime={seance.heure_fin}>{seance.heure_fin.slice(0, 5)}</time>
      </div>
      <div className={styles.sessionBody}>
        <h3>{seance.matiere || "Séance"}</h3>
        <p className={styles.teacher}>{seance.enseignant}</p>
        <div className={styles.sessionMeta}>
          <span>
            <MapPin size={12} />
            {seance.salle}
          </span>
          {seance.groupe && <span>{seance.groupe}</span>}
          <span
            className={`${styles.status} ${statut === "present" ? styles.present : statut === "absent" ? styles.absent : styles.pending}`}
          >
            <Icon size={12} strokeWidth={2.3} />
            {label}
          </span>
        </div>
      </div>
    </article>
  );
}
