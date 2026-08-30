# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

"Présence" is a class-attendance (roll call) system for a university campus (timezone `Africa/Douala` is hardcoded in [salaireprof.php](salaireprof.php) — Cameroon). It replaces manual attendance sheets: a student delegate geolocates the classroom, students confirm presence from their phones by matching GPS distance, teachers' actual session times are logged, and admins turn that into attendance PDFs and teacher-pay reports.

Plain PHP, no framework, no build step, no package manager at the project root (this is not a git repository). Two independent apps live side by side:

- **Root (`/`)** — the student/teacher/delegate-facing app.
- **`superprotect/`** — a separate admin back-office app with its own login, its own `db.php`, and its own copy of TCPDF. Despite the name, it is not more secure: [superprotect/login.php](superprotect/login.php) checks against hardcoded credentials (`admin123` / `passadmin123`) in plaintext, no `password_hash`. Treat this whole directory as legacy/insecure and a prime candidate for hardening if the project is taken further.

## Running it locally

There is no CLI build/test/lint tooling — this is a classic "drop into an Apache docroot" PHP app (XAMPP/LAMPP, matching the `/opt/lampp/htdocs/...` path it lives in).

- Serve the folder with Apache+PHP (XAMPP) or `php -S localhost:8000` from the project root.
- `superprotect/` has its own `composer.json` (`dompdf/dompdf`) with a `vendor/` already checked in — no `composer install` needed unless dependencies change.
- The root app's PDF generation ([liste.php](liste.php), [salaireprof.php](salaireprof.php)) uses the bundled `includes/tcpdf/` library directly (not via Composer).
- No automated tests exist in this repo.

## ⚠️ Two separate, inconsistent DB configs — read this before debugging "missing data"

- [includes/config.php](includes/config.php) (root app): connects to a **local** MySQL, `PrésenceBase`, host `127.0.0.1`, user `root`, no password.
- [superprotect/includes/db.php](superprotect/includes/db.php) (admin app): connects to a **remote InfinityFree-hosted** MySQL database with different credentials hardcoded in the file.

These are two different physical databases pointing at (presumably) the same schema. If the admin panel and the student/teacher app appear to disagree about data, this mismatch is almost certainly why. Before any "make admin and app share data" work, first decide/confirm which DB is authoritative and repoint one of the two `config.php`/`db.php` files — don't assume they're already in sync.

Both configs have credentials committed in plaintext in the repo.

## Core domain model (MySQL, no migrations — inspect the live schema directly)

Key tables referenced across the code: `users`, `niveaux` (year/level, e.g. L1/L2/L3), `filieres` (programs, belong to a `niveau`), `salles` (classrooms, belong to a `filiere`), `matieres` (subjects), `cours` (courses, link `matiere`), `seances` (scheduled sessions, link `cours` + `salle` + `enseignant_id`), `emplois_temps` / `semaines` (timetables/weeks), `positions_seances` (delegate GPS pin per session), `presences_etudiants` (per-student attendance), `requetes_enseignants` (teacher disputes/justifications for a session), `promotions_temporaires` (temporary student→delegate elevation), `tarifs_heures` (hourly pay rate per `niveau`), `pushes` (session reschedule/push requests).

`users.grade` is the role enum: `Etudiant`, `Delegue`, `Enseignant` (admins are separate, session-only, in `superprotect/`). `users.validated` gates `Delegue`/`Enseignant` accounts through `none` → `pending` → `yes`/rejected (see Auth below). `Delegue`/`Etudiant` users also carry `classroom` (a `salles.nom` value), which is how they're scoped to a room's sessions.

## Auth & session model

All auth logic lives in [includes/functions.php](includes/functions.php) and is re-included (via `require_once`) at the top of every root-app page — there is no router or shared bootstrap/layout file, each page is a standalone script.

- Login is in [index.php](index.php): looks up `users` by `(name, grade)` + `password_verify`, then branches by grade.
- Role checks: `isLoggedIn()`, `isTeacher()`, `isDelegate()`, `isStudent()` — each page manually calls the relevant guard(s) at the top and redirects to `index.php` if unauthorized. There's no centralized middleware; adding a new protected page means copy-pasting the same guard pattern seen in every existing file.
- `Delegue` and `Enseignant` accounts require admin validation (`validated` column) before use: unvalidated delegates land on [validation.php](validation.php), unvalidated teachers on [validation_enseignant_user.php](validation_enseignant_user.php). Validation itself happens from the `superprotect/` admin panel ([superprotect/validate.php](superprotect/validate.php), [superprotect/validate_teacher.php](superprotect/validate_teacher.php)).
- **Temporary promotion**: a teacher can grant a student delegate powers for a limited time ([promotion_temporaire.php](promotion_temporaire.php) writes `promotions_temporaires`). On next login, `index.php` checks for an active row in that table and *overrides* the session `grade` to `Delegue` for the duration — this is a login-time side effect, easy to miss when tracing "why does this student have delegate access."
- `generateSessionIdentifier()` hashes `user_id+name+grade+user_agent+ip+time` into `$_SESSION['session_identifier']`; `verifySessionConsistency()` is meant to detect session hijack/inconsistency, but note it re-derives the hash including the current `time()`, so it can never actually match a stored value computed at a different second — check call sites before relying on this function.

## Attendance ("presence") flow — the core feature

1. A `Delegue` opens [dashboard.php](dashboard.php), sees today's `seances` for their `classroom`, and for the current session calls [position.php](position.php) which geolocates their browser and upserts `positions_seances (seance_id, delegue_id, latitude, longitude)`.
2. Each `Etudiant` on [dashEtudiant.php](dashEtudiant.php) geolocates their own browser and calls [check_distance.php](check_distance.php) (JSON endpoint) which Haversine-computes the distance to the delegate's pinned position for that `seance_id` and returns `within_range` against `$max_distance` (currently `2550` meters — the in-code comment says "increased to 650m," so the constant and the comment have drifted; confirm the intended value before changing it).
3. If within range the student confirms presence, and the delegate reviews/finalizes the roll call in [liste.php](liste.php), which also generates the printable PDF attendance sheet (`generatePresencePDF()`, via TCPDF) and writes `presences_etudiants`.
4. Teachers can dispute a marked absence/penalty for a session via [requete.php](requete.php) → `requetes_enseignants` (helpers in [includes/functions.php](includes/functions.php) — note this file's docblock calls it "fonctions spécifiques aux requêtes enseignants" even though it's the shared `includes/`, not `superprotect/`). Admin approval in `superprotect/` flips `seances.etat_prof`/`etat_final` and back-fills `debut_reel`/`fin_reelle`.
5. Teacher pay is derived from actual vs. scheduled session times: [salaireprof.php](salaireprof.php) joins `seances` + `tarifs_heures` (rate keyed by `niveau`) and computes `retard_minutes` / `duree_effectuee_minutes` via `TIMESTAMPDIFF`, with a PDF export path.

## Admin app (`superprotect/`)

Session-based (`$_SESSION['admin_logged_in']`), single shared admin login (see security note above), no per-admin accounts. Each page is again a standalone script (`include 'includes/db.php'`/`admin-header.php`/`admin-footer.php`, no shared router). Functional areas, all under `superprotect/`:

- Structure/catalog CRUD: [niveau.php](superprotect/niveau.php), [filiere.php](superprotect/filiere.php), [classrooms.php](superprotect/classrooms.php)/[add-classroom.php](superprotect/add-classroom.php), [GestionMatiere.php](superprotect/GestionMatiere.php) (subjects), [create_niveau.php](superprotect/create_niveau.php), [create_filiere.php](superprotect/create_filiere.php), [create_salle.php](superprotect/create_salle.php).
- Scheduling: [generer_emplois.php](superprotect/generer_emplois.php) (generates timetables/HTML), [add_seance.php](superprotect/add_seance.php)/[delete_seance.php](superprotect/delete_seance.php).
- Attendance oversight: [presence.php](superprotect/presence.php) (PDF attendance list generation, `generatePdfContent()`), [historique_seances.php](superprotect/historique_seances.php), [export_historique.php](superprotect/export_historique.php), [generer_rapport.php](superprotect/generer_rapport.php).
- People/validation: [validation.php](superprotect/validation.php)/[validate.php](superprotect/validate.php) (delegates), [validation_enseignant.php](superprotect/validation_enseignant.php)/[validate_teacher.php](superprotect/validate_teacher.php) (teachers), [designation.php](superprotect/designation.php)/[designation_enseignant.php](superprotect/designation_enseignant.php).
- Teacher requests/pay: [requetes.php](superprotect/requetes.php)/[process_request.php](superprotect/process_request.php) (mirrors root's `requete.php` flow from the admin side), [suiviHeurProf.php](superprotect/suiviHeurProf.php), [suivi_salaires.php](superprotect/suivi_salaires.php), [gestion_tarifs.php](superprotect/gestion_tarifs.php) (hourly rates), [details_prof.php](superprotect/details_prof.php).

## Conventions to know before editing

- No shared header/footer/layout in the root app (each page inlines its own `<style>` in a `:root { --violet-... }` violet/neon theme); `superprotect/` does have `includes/admin-header.php`/`admin-footer.php` shared partials.
- User-facing text and all identifiers (`grade` values, column names) are French; keep new code consistent with that (`Enseignant`, `Etudiant`, `Delegue`, `seance`, `salle`, `filiere`, `niveau`, `matiere`).
- Uploaded proof files for teacher disputes go to `uploads/requetes/`.
- Both apps bundle their own copy of TCPDF under `includes/tcpdf/` (root) and `superprotect/includes/tcpdf/` (admin) — these are vendored, not managed by Composer; `superprotect/vendor/` (Composer) is only for `dompdf`.
