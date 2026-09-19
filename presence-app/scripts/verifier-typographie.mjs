// Garde-fou de l'échelle typographique (voir globals.css) : échoue dès qu'une
// feuille ou un composant réintroduit une taille en pixels hors échelle, une
// graisse numérique, un letter-spacing, de l'italique ou des capitales —
// exactement ce qui donnait à l'application son air « généré » (42 tailles
// différentes, du 9 px, des graisses 550/650…).
//
//   npm run lint:typo
import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

// fileURLToPath plutôt que .pathname : le chemin du dépôt contient un accent.
const RACINE = join(dirname(fileURLToPath(import.meta.url)), "..", "src");

const REGLES_CSS = [
  [/font-size:\s*[0-9.]+(px|rem|em)/g, "taille en dur — utiliser var(--text-13…56)"],
  [/font-weight:\s*[0-9]{3}\b/g, "graisse numérique — utiliser var(--w-regular|semibold|bold)"],
  [/letter-spacing:(?!\s*var\(--tracking-title\))/g, "letter-spacing — seul var(--tracking-title) est permis, sur un titre"],
  [/font-style:\s*italic/g, "italique — aucune voix italique dans l'application"],
  [/text-transform:\s*uppercase/g, "capitales espacées — écrire le texte tel qu'on le lit"],
];

const REGLES_TSX = [
  [/\btext-\[[0-9.]+px\]/g, "taille arbitraire — utiliser text-xs…text-4xl"],
  [/\btracking-(wide|wider|widest|tighter)\b/g, "tracking — seul tracking-tight (titres) est permis"],
  [/\buppercase\b/g, "capitales — écrire le texte tel qu'on le lit"],
  [/\bitalic\b/g, "italique — aucune voix italique dans l'application"],
];

const fichiers = [];
(function parcourir(dossier) {
  for (const nom of readdirSync(dossier)) {
    const chemin = join(dossier, nom);
    if (statSync(chemin).isDirectory()) parcourir(chemin);
    else if (/\.(css|tsx)$/.test(nom) && !chemin.includes("/components/ui/")) fichiers.push(chemin);
  }
})(RACINE);

const ecarts = [];
for (const chemin of fichiers) {
  const contenu = readFileSync(chemin, "utf8");
  // globals.css définit l'échelle elle-même : ses déclarations de variables sont légitimes.
  const regles = chemin.endsWith(".css") ? REGLES_CSS : REGLES_TSX;
  for (const [motif, message] of regles) {
    for (const m of contenu.matchAll(motif)) {
      const ligne = contenu.slice(0, m.index).split("\n").length;
      const texte = contenu.split("\n")[ligne - 1].trim();
      if (chemin.endsWith("globals.css") && /^--/.test(texte)) continue;
      ecarts.push(`${relative(process.cwd(), chemin)}:${ligne}  ${message}\n    ${texte}`);
    }
  }
}

if (ecarts.length) {
  console.error(`Typographie : ${ecarts.length} écart(s) à l'échelle.\n\n${ecarts.join("\n")}\n`);
  process.exit(1);
}
console.log(`Typographie : ${fichiers.length} fichiers, aucun écart à l'échelle.`);
