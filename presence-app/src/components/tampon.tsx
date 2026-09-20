/**
 * Le tampon « Présent » : l'application remplace une feuille d'émargement,
 * et l'encre qui marque la présence est le geste dont on se souvient. Un
 * cadre double, une encre verte un peu inégale, posé de travers comme un
 * vrai coup de tampon — animé quand il vient d'être donné.
 */
export function Tampon({
  mot = "Présent",
  detail,
  ton = "present",
  taille = "normal",
  anime = false,
  className = "",
}: {
  mot?: string;
  detail?: string;
  ton?: "present" | "absent";
  taille?: "normal" | "mini";
  anime?: boolean;
  className?: string;
}) {
  return (
    <span
      role="img"
      aria-label={detail ? `${mot}, ${detail}` : mot}
      className={`tampon tampon-${ton} tampon-${taille} ${anime ? "tampon-anime" : ""} ${className}`}
    >
      <span className="tampon-mot">{mot}</span>
      {detail && <span className="tampon-detail">{detail}</span>}
    </span>
  );
}
