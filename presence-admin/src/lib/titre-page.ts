/**
 * Titre de section affiché dans l'en-tête, dérivé du chemin plutôt que
 * répété page par page — une seule source à tenir à jour quand une section
 * est renommée ou ajoutée.
 */
const TITRES: Record<string, string> = {
  "/dashboard": "Vue d'ensemble",
  "/catalogue": "Catalogue",
  "/emplois-du-temps": "Emplois du temps",
  "/etudiants": "Étudiants",
  "/validations": "Validations",
  "/requetes": "Requêtes enseignants",
  "/demandes-formation": "Migrations FA → FI",
  "/tarifs": "Tarifs horaires",
  "/historique": "Historique des séances",
  "/reconnaissance-faciale": "Reconnaissance faciale",
};

export function titrePage(pathname: string): string {
  return TITRES[pathname] ?? "Présence";
}
