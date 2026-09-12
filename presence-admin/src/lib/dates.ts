/**
 * Dates de calendrier et heure de référence.
 *
 * L'API sérialise les dates de calendrier en « AAAA-MM-JJ ». Les passer à
 * `new Date("2026-09-14")` les interprète à minuit UTC : affichées depuis
 * un navigateur à l'ouest de Greenwich, elles reculeraient d'un jour. On les
 * découpe donc à la main et on ne construit que des dates *locales*, qui
 * gardent le même jour civil quel que soit le fuseau de la machine.
 */

export const FUSEAU = "Africa/Douala";

export type Ymd = string;

export function dateLocale(ymd: Ymd): Date {
  const [a, m, j] = ymd.split("-").map(Number);
  return new Date(a, m - 1, j);
}

export function versYmd(date: Date): Ymd {
  const p = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`;
}

export function ajouterJours(ymd: Ymd, jours: number): Ymd {
  const d = dateLocale(ymd);
  d.setDate(d.getDate() + jours);
  return versYmd(d);
}

export function formaterYmd(ymd: Ymd, options: Intl.DateTimeFormatOptions): string {
  return dateLocale(ymd).toLocaleDateString("fr-FR", options);
}

/** « 14 → 20 sept. » */
export function plageSemaine(debut: Ymd, fin: Ymd): string {
  const memeMois = debut.slice(0, 7) === fin.slice(0, 7);
  const d = formaterYmd(debut, memeMois ? { day: "numeric" } : { day: "numeric", month: "short" });
  const f = formaterYmd(fin, { day: "numeric", month: "short" });
  return `${d} → ${f}`;
}

/** Minutes depuis minuit d'une heure « HH:MM » ou « HH:MM:SS ». */
export function minutes(heure: string): number {
  const [h, m] = heure.split(":").map(Number);
  return h * 60 + m;
}

export function hhmm(heure: string): string {
  return heure.slice(0, 5);
}

/**
 * Jour civil et minute de la journée d'un instant, vus depuis Douala —
 * quel que soit le fuseau réglé sur la machine qui affiche la grille.
 */
export function instantADouala(instant: Date): { date: Ymd; minute: number } {
  const parties = new Intl.DateTimeFormat("en-CA", {
    timeZone: FUSEAU,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(instant);
  const v = (type: string) => parties.find((p) => p.type === type)?.value ?? "00";

  return {
    date: `${v("year")}-${v("month")}-${v("day")}`,
    minute: Number(v("hour")) * 60 + Number(v("minute")),
  };
}
