export type Plateforme = "ios" | "android" | "bureau";

/**
 * Plateforme du navigateur courant, uniquement pour adapter les instructions
 * de réactivation d'une permission — leur chemin diffère complètement d'un
 * système à l'autre.
 *
 * L'iPad se déclare comme un Mac depuis iPadOS 13 : seule la présence d'un
 * écran tactile permet encore de le distinguer d'un vrai ordinateur.
 */
export function plateforme(): Plateforme {
  if (typeof navigator === "undefined") return "bureau";

  const ua = navigator.userAgent;

  if (/iPhone|iPad|iPod/.test(ua)) return "ios";
  if (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1) return "ios";
  if (/Android/.test(ua)) return "android";

  return "bureau";
}

/**
 * Vrai dans les navigateurs intégrés (WhatsApp, Instagram, Facebook), où les
 * permissions sont souvent refusées d'office sans que l'utilisateur puisse
 * rien y faire depuis cette fenêtre — la seule issue est d'ouvrir le lien
 * dans le vrai navigateur.
 */
export function estNavigateurIntegre(): boolean {
  if (typeof navigator === "undefined") return false;

  return /FBAN|FBAV|Instagram|Line\/|WhatsApp/.test(navigator.userAgent);
}

export type TypePermission = "position" | "camera";

/**
 * Chemin exact pour réactiver une permission refusée.
 *
 * Aucune application, native comprise, ne peut rouvrir un dialogue de
 * permission déjà refusé : le navigateur mémorise le refus et n'affiche plus
 * rien. Ces instructions sont donc la seule issue réelle — d'où l'intérêt
 * qu'elles soient exactes plutôt que génériques.
 */
export function instructionsReactivation(type: TypePermission): string[] {
  const libelle = type === "position" ? "Position" : "Caméra";

  switch (plateforme()) {
    case "ios":
      return [
        `Touchez « AA » à gauche de la barre d'adresse.`,
        `Choisissez « Réglages du site web ».`,
        `Passez ${libelle} sur « Autoriser », puis rechargez la page.`,
        `Si l'option est absente : Réglages ▸ Safari ▸ ${libelle}.`,
      ];
    case "android":
      return [
        `Touchez le cadenas à gauche de la barre d'adresse.`,
        `Ouvrez « Autorisations » (ou « Paramètres du site »).`,
        `Passez ${libelle} sur « Autoriser », puis rechargez la page.`,
      ];
    default:
      return [
        `Cliquez sur le cadenas à gauche de la barre d'adresse.`,
        `Passez ${libelle} sur « Autoriser ».`,
        `Rechargez la page.`,
      ];
  }
}
