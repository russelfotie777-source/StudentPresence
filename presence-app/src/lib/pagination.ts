import type { Paginated } from "@/types/api";

/**
 * Page suivante à demander, ou `undefined` quand on est au bout — signature
 * attendue par `getNextPageParam` de useInfiniteQuery.
 */
export function pageSuivante<T>(derniere: Paginated<T>): number | undefined {
  return derniere.meta.current_page < derniere.meta.last_page
    ? derniere.meta.current_page + 1
    : undefined;
}

/**
 * Aplatit les pages accumulées en une seule liste à afficher.
 */
export function lignes<T>(pages: Paginated<T>[] | undefined): T[] {
  return pages?.flatMap((page) => page.data) ?? [];
}

/**
 * Total renvoyé par le serveur, toutes pages confondues — sert à afficher
 * « 20 sur 57 » à côté du bouton « Voir plus ».
 */
export function total<T>(pages: Paginated<T>[] | undefined): number {
  return pages?.[0]?.meta.total ?? 0;
}
