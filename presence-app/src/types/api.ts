export type UserRole = "Etudiant" | "Delegue" | "Enseignant" | "Admin";
export type ValidationStatus = "none" | "pending" | "approved";
export type FormationType = "FI" | "FA" | "FM";
export type PresenceState = "present" | "absent";
export type PushStatus = "pending" | "approved" | "rejected";
export type RequestStatus = "en_attente" | "acceptee" | "rejetee";
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
  role: UserRole;
  effective_role: UserRole;
  validation_status: ValidationStatus;
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
  etat_final: PresenceState;
  presences_locked: boolean;
  is_active: boolean;
  is_past: boolean;
  matiere?: string;
  push?: { etudiants_presents: number; status: PushStatus } | null;
  ma_presence?: PresenceState | null;
  position_envoyee?: boolean;
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
  salle_cible?: { id: number; nom: string } | null;
  motif: string | null;
  statut: RequestStatus;
  date_creation: string;
  date_traitement: string | null;
  commentaire_admin: string | null;
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
