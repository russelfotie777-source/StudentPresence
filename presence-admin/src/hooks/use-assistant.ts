"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api-client";

export type TypeAction =
  | "creer_cours"
  | "inscrire_etudiant"
  | "creer_enseignant"
  | "modifier_seance"
  | "supprimer_seance"
  | "supprimer_cours"
  | "changer_salle_etudiant";

export type StatutAction = "en_attente" | "appliquee" | "echouee" | "ignoree";

export interface ActionIA {
  id: string;
  type: TypeAction;
  resume: string;
  parametres: Record<string, unknown>;
  statut: StatutAction;
  resultat: { ok: boolean; message: string; details: Record<string, unknown> } | null;
  proposee_le: string;
  appliquee_le?: string;
}

export interface MessageIA {
  role: "user" | "assistant";
  texte: string;
}

export interface ConversationIA {
  id: number;
  titre: string | null;
  messages: MessageIA[];
  actions: ActionIA[];
  mise_a_jour: string | null;
}

export interface ResumeConversation {
  id: number;
  titre: string | null;
  mise_a_jour: string | null;
  actions_en_attente: number;
}

export interface FichierJoint {
  nom: string;
  type: string;
  base64: string;
}

export function useEtatAssistant() {
  return useQuery({
    queryKey: ["assistant", "etat"],
    queryFn: () => apiFetch<{ disponible: boolean; modele: string; types_fichiers: string[] }>("/api/assistant"),
    staleTime: 5 * 60_000,
  });
}

export function useConversations(enabled: boolean) {
  return useQuery({
    queryKey: ["assistant", "conversations"],
    queryFn: () => apiFetch<ResumeConversation[]>("/api/assistant/conversations"),
    enabled,
  });
}

export function useConversation(id: number | null) {
  return useQuery({
    queryKey: ["assistant", "conversation", id],
    queryFn: () => apiFetch<ConversationIA>(`/api/assistant/conversations/${id}`),
    enabled: id !== null,
  });
}

export function useCreerConversation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => apiFetch<ConversationIA>("/api/assistant/conversations", { method: "POST" }),
    onSuccess: (c) => {
      queryClient.setQueryData(["assistant", "conversation", c.id], c);
      queryClient.invalidateQueries({ queryKey: ["assistant", "conversations"] });
    },
  });
}

export function useSupprimerConversation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => apiFetch(`/api/assistant/conversations/${id}`, { method: "DELETE" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["assistant", "conversations"] }),
  });
}

interface ReponseEnvoi {
  reponse: string;
  actions: ActionIA[];
  nouvelles: string[];
  conversation: ConversationIA;
  jetons: { entree: number; sortie: number };
}

/**
 * Un message peut déclencher plusieurs allers-retours avec le modèle
 * (lecture du référentiel, propositions) : la réponse prend de quelques
 * secondes à une minute pour un emploi du temps complet.
 */
export function useEnvoyerMessage(conversationId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (v: { texte: string; fichiers: FichierJoint[] }) =>
      apiFetch<ReponseEnvoi>(`/api/assistant/conversations/${conversationId}/messages`, {
        method: "POST",
        body: JSON.stringify(v),
      }),
    onSuccess: (r) => {
      queryClient.setQueryData(["assistant", "conversation", r.conversation.id], r.conversation);
      queryClient.invalidateQueries({ queryKey: ["assistant", "conversations"] });
    },
  });
}

/** Tout ce que les actions peuvent avoir changé, pour que les autres écrans suivent. */
const CLES_A_RAFRAICHIR = [
  "emploi-du-temps", "course-templates", "historique-seances", "dashboard", "semaines",
  "feuille-presence", "etudiants", "matieres", "enseignants", "salles",
];

export function useAppliquerActions(conversationId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (ids: string[]) =>
      apiFetch<{ appliquees: number; echouees: number; actions: ActionIA[] }>(
        `/api/assistant/conversations/${conversationId}/appliquer`,
        { method: "POST", body: JSON.stringify({ ids }) },
      ),
    onSuccess: (r) => {
      queryClient.setQueryData<ConversationIA | undefined>(
        ["assistant", "conversation", conversationId],
        (c) => (c ? { ...c, actions: r.actions } : c),
      );
      queryClient.invalidateQueries({ queryKey: ["assistant", "conversations"] });
      for (const cle of CLES_A_RAFRAICHIR) queryClient.invalidateQueries({ queryKey: [cle] });
    },
  });
}

export function useIgnorerActions(conversationId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (ids: string[]) =>
      apiFetch<{ actions: ActionIA[] }>(`/api/assistant/conversations/${conversationId}/ignorer`, {
        method: "POST",
        body: JSON.stringify({ ids }),
      }),
    onSuccess: (r) => {
      queryClient.setQueryData<ConversationIA | undefined>(
        ["assistant", "conversation", conversationId],
        (c) => (c ? { ...c, actions: r.actions } : c),
      );
      queryClient.invalidateQueries({ queryKey: ["assistant", "conversations"] });
    },
  });
}
