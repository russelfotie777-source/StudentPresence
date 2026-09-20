export type UserRole = "Etudiant" | "Delegue" | "Enseignant" | "Admin";
export type ValidationStatus = "none" | "pending" | "approved";
export type FormationType = "FI" | "FA" | "FM";
export type PresenceState = "present" | "absent";
export type PushStatus = "pending" | "approved" | "rejected";
export type RequestStatus = "en_attente" | "acceptee" | "rejetee";
export type StatutCompte = "actif" | "restreint" | "bloque";
export type Weekday =
  | "LUNDI"
  | "MARDI"
  | "MERCREDI"
  | "JEUDI"
  | "VENDREDI"
  | "SAMEDI"
  | "DIMANCHE";

export interface User {
  id: number;
  name: string;
  phone: string;
  email: string | null;
  /** Une adresse ne compte qu'une fois confirmée par son code : c'est elle qui reçoit le mot de passe oublié. */
  email_verifie: boolean;
  /** Le mot de passe initial commun est encore en place : tout est fermé tant qu'il n'est pas remplacé. */
  doit_changer_mot_de_passe: boolean;
  role: UserRole;
  effective_role: UserRole;
  validation_status: ValidationStatus;
  statut_compte: StatutCompte;
  motif_statut: string | null;
  statut_modifie_le: string | null;
  formation: FormationType | null;
  salle: { id: number; nom: string } | null;
  niveau: { id: number; nom: string } | null;
  filiere: { id: number; nom: string } | null;
  quota: number;
  has_active_promotion: boolean;
}

export interface AuthResponse {
  user: User;
  token?: string;
  requires_face?: boolean;
  face_enrolled?: boolean;
}

export interface MeResponse {
  user: User;
  face_pending?: boolean;
  face_enrolled?: boolean;
  /** Adresse dont un code de vérification est encore attendu. */
  email_en_attente?: string | null;
}

export interface Seance {
  id: number;
  salle: string;
  enseignant: string;
  groupe: string;
  date_seance: string | null;
  jour: Weekday;
  heure_debut: string;
  heure_fin: string;
  debut_reel: string | null;
  fin_reelle: string | null;
  etat_delegue: PresenceState | null;
  etat_prof: PresenceState | null;
  /** L'état enseignant a été donné par le délégué à sa place. */
  etat_prof_par_delegue?: boolean;
  /** Règle admin : le délégué peut confirmer la présence de l'enseignant à sa place. */
  confirmation_enseignant_par_delegue?: boolean;
  etat_final: PresenceState;
  presences_locked: boolean;
  is_active: boolean;
  is_past: boolean;
  matiere?: string;
  push?: { etudiants_presents: number; status: PushStatus } | null;
  ma_presence?: PresenceState | null;
  /** Heure du pointage (« 09h12 »), heure de Douala. */
  ma_presence_a?: string | null;
  position_envoyee?: boolean;
  geolocation?: {
    max_position_accuracy_meters: number;
    max_check_in_accuracy_meters: number;
  };
}

export interface RosterEntry {
  id: number;
  name: string;
  formation: FormationType | null;
  etat: PresenceState | null;
}

export interface RequeteEnseignant {
  id: number;
  seance_id: number;
  enseignant?: string;
  matiere: string;
  salle: string;
  niveau: string;
  heure_seance: string | null;
  description: string;
  preuve_url: string | null;
  statut: RequestStatus;
  date_creation: string;
  date_traitement: string | null;
  commentaire_admin: string | null;
}

export interface DemandeFormation {
  id: number;
  salle_cible?: { id: number; nom: string; filiere?: string | null; niveau?: string | null } | null;
  motif: string | null;
  statut: RequestStatus;
  date_creation: string;
  date_traitement: string | null;
  commentaire_admin: string | null;
}

/** Ce que l'onglet Migration montre à un étudiant : sa situation et ce qu'il peut demander. */
export interface SituationMigration {
  formation: FormationType | null;
  salle: { id: number; nom: string; formation: "FI" | "FA" } | null;
  niveau: string | null;
  filiere: string | null;
  departement: { code: string; nom: string } | null;
  niveau_max: number;
  eligible: boolean;
  empechement: string | null;
  salles: { id: number; nom: string; filiere: string; niveau: string; effectif: number }[];
  demande_en_attente: {
    id: number;
    salle_cible: { id: number; nom: string } | null;
    motif: string | null;
    date_creation: string | null;
  } | null;
}

export interface PayrollLine {
  seance_id: number;
  date: string | null;
  matiere: string | null;
  salle: string;
  heure_debut: string;
  debut_reel: string | null;
  fin_reelle: string | null;
  retard_minutes: number;
  duree_minutes: number;
  tarif_plein: number;
  salaire: number;
  penalite_retard: number;
}

export interface PayrollSummary {
  total_salaire: number;
  total_penalite_retard: number;
  total_minutes: number;
  lignes: PayrollLine[];
}

export interface ApiValidationError {
  message: string;
  errors?: Record<string, string[]>;
}

/**
 * Enveloppe renvoyée par les listes paginées de l'API (ressources Laravel).
 * Consommée via useInfiniteQuery + bouton « Voir plus ».
 */
export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface Notification {
  id: string;
  type: string;
  statut?: StatutCompte;
  titre: string;
  message: string;
  motif?: string | null;
  lue: boolean;
  date: string;
}
