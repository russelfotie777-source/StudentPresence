"use client";

import { useEffect, useRef, useState } from "react";
import {
  CalendarClock,
  FileText,
  GraduationCap,
  History,
  ImageIcon,
  KeyRound,
  Loader2,
  Paperclip,
  Pencil,
  Plus,
  SendHorizontal,
  Sparkles,
  Trash2,
  X,
} from "lucide-react";
import { toast } from "sonner";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  useConversation,
  useConversations,
  useCreerConversation,
  useEnvoyerMessage,
  useEtatAssistant,
  useSupprimerConversation,
  type FichierJoint,
  type MessageIA,
} from "@/hooks/use-assistant";
import { ApiError } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { ActionsProposees } from "@/components/assistant/actions-proposees";

const TAILLE_MAX = 8 * 1024 * 1024;

const SUGGESTIONS = [
  {
    icon: CalendarClock,
    titre: "Importer un emploi du temps",
    texte: "Voici l'emploi du temps de la salle … : crée les cours correspondants pour tout le semestre.",
    fichier: true,
  },
  {
    icon: GraduationCap,
    titre: "Inscrire des étudiants",
    texte: "Inscris ces étudiants dans la salle … (formation FI) :\n- NOM Prénom, matricule …\n- …",
    fichier: false,
  },
  {
    icon: Pencil,
    titre: "Déplacer ou annuler une séance",
    texte: "Dans la salle …, déplace la séance de … de jeudi à vendredi même heure.",
    fichier: false,
  },
];

/**
 * L'assistant IA du back-office. Il lit un emploi du temps (PDF, photo ou
 * texte), une liste d'étudiants ou une consigne, consulte les données
 * réelles et propose des actions : rien n'est écrit tant que l'admin n'a
 * pas cliqué « Appliquer ».
 */
export function AssistantPanel() {
  const [ouvert, setOuvert] = useState(false);
  const etat = useEtatAssistant();
  const disponible = etat.data?.disponible ?? false;

  const conversations = useConversations(ouvert && disponible);
  const [conversationId, setConversationId] = useState<number | null>(null);
  const conversation = useConversation(conversationId);
  const creer = useCreerConversation();
  const supprimer = useSupprimerConversation();

  // À l'ouverture, on reprend la dernière conversation ; sinon on en crée une.
  const idCourant =
    conversationId ?? (conversations.data && conversations.data.length > 0 ? conversations.data[0].id : null);

  function nouvelleConversation() {
    creer.mutate(undefined, { onSuccess: (c) => setConversationId(c.id) });
  }

  return (
    <Sheet open={ouvert} onOpenChange={setOuvert}>
      <SheetTrigger
        render={
          <Button variant="outline" size="sm" className="gap-1.5">
            <Sparkles className="size-3.5" />
            <span className="hidden sm:inline">Assistant IA</span>
          </Button>
        }
      />
      <SheetContent side="right" className="w-full bg-card text-card-foreground sm:w-[520px] sm:max-w-[92vw]">
        <div className="flex h-full flex-col">
          <header className="flex items-center gap-2.5 border-b border-border px-4 py-3 pr-12">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10">
              <Sparkles className="size-[18px] text-primary" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-foreground">Assistant IA</p>
              <p className="truncate text-xs text-muted-foreground">
                {etat.isLoading
                  ? "Vérification…"
                  : disponible
                    ? "Propose, vous appliquez"
                    : "Non configuré"}
              </p>
            </div>
            {disponible && (
              <div className="flex items-center gap-1">
                <DropdownMenu>
                  <DropdownMenuTrigger
                    render={<Button variant="ghost" size="icon-sm" aria-label="Conversations précédentes" />}
                  >
                    <History className="size-4" />
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-72">
                    <DropdownMenuLabel>Conversations</DropdownMenuLabel>
                    {conversations.data?.length === 0 && (
                      <p className="px-2 py-1.5 text-xs text-muted-foreground">Aucune pour l&apos;instant.</p>
                    )}
                    {conversations.data?.map((c) => (
                      <DropdownMenuItem key={c.id} onClick={() => setConversationId(c.id)}>
                        <span className="min-w-0 flex-1 truncate">{c.titre ?? "Nouvelle conversation"}</span>
                        {c.actions_en_attente > 0 && (
                          <span className="ml-2 rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                            {c.actions_en_attente}
                          </span>
                        )}
                      </DropdownMenuItem>
                    ))}
                    {idCourant !== null && (
                      <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                          variant="destructive"
                          onClick={() =>
                            supprimer.mutate(idCourant, {
                              onSuccess: () => {
                                setConversationId(null);
                                toast.success("Conversation supprimée.");
                              },
                            })
                          }
                        >
                          <Trash2 className="size-4" /> Supprimer la conversation courante
                        </DropdownMenuItem>
                      </>
                    )}
                  </DropdownMenuContent>
                </DropdownMenu>
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label="Nouvelle conversation"
                  disabled={creer.isPending}
                  onClick={nouvelleConversation}
                >
                  <Plus className="size-4" />
                </Button>
              </div>
            )}
          </header>

          {!etat.isLoading && !disponible ? (
            <NonConfigure />
          ) : (
            <Conversation
              conversationId={idCourant}
              messages={conversation.data?.messages ?? []}
              actions={conversation.data?.actions ?? []}
              chargement={conversation.isLoading || (conversationId === null && conversations.isLoading)}
              typesFichiers={etat.data?.types_fichiers ?? []}
              onCreer={nouvelleConversation}
              creation={creer.isPending}
            />
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}

function NonConfigure() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
      <div className="flex size-12 items-center justify-center rounded-2xl bg-muted">
        <KeyRound className="size-6 text-muted-foreground" />
      </div>
      <div className="max-w-xs">
        <p className="text-sm font-semibold text-foreground">L&apos;assistant n&apos;est pas configuré</p>
        <p className="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
          Renseignez <code className="rounded bg-muted px-1 py-0.5 text-[12px]">ANTHROPIC_API_KEY</code> dans
          le fichier <code className="rounded bg-muted px-1 py-0.5 text-[12px]">.env</code> de l&apos;API, puis
          rechargez cette page.
        </p>
      </div>
    </div>
  );
}

function Conversation({
  conversationId,
  messages,
  actions,
  chargement,
  typesFichiers,
  onCreer,
  creation,
}: {
  conversationId: number | null;
  messages: MessageIA[];
  actions: Parameters<typeof ActionsProposees>[0]["actions"];
  chargement: boolean;
  typesFichiers: string[];
  onCreer: () => void;
  creation: boolean;
}) {
  const envoyer = useEnvoyerMessage(conversationId);
  const [texte, setTexte] = useState("");
  const [fichiers, setFichiers] = useState<FichierJoint[]>([]);
  const [enAttente, setEnAttente] = useState<string | null>(null);
  const fil = useRef<HTMLDivElement>(null);
  const saisie = useRef<HTMLInputElement>(null);

  useEffect(() => {
    fil.current?.scrollTo({ top: fil.current.scrollHeight, behavior: "smooth" });
  }, [messages.length, enAttente, actions.length]);

  async function joindre(liste: FileList | null) {
    if (!liste) return;
    const ajouts: FichierJoint[] = [];
    for (const f of Array.from(liste).slice(0, 3 - fichiers.length)) {
      if (!typesFichiers.includes(f.type)) {
        toast.error(`${f.name} : format non pris en charge (PDF ou image).`);
        continue;
      }
      if (f.size > TAILLE_MAX) {
        toast.error(`${f.name} dépasse 8 Mo.`);
        continue;
      }
      const base64 = await new Promise<string>((res, rej) => {
        const r = new FileReader();
        r.onload = () => res(String(r.result).split(",")[1] ?? "");
        r.onerror = () => rej(r.error);
        r.readAsDataURL(f);
      });
      ajouts.push({ nom: f.name, type: f.type, base64 });
    }
    setFichiers((prev) => [...prev, ...ajouts]);
  }

  function soumettre() {
    if (conversationId === null || envoyer.isPending) return;
    const contenu = texte.trim();
    if (!contenu && fichiers.length === 0) return;

    setEnAttente(contenu || `${fichiers.length} fichier${fichiers.length > 1 ? "s" : ""} joint${fichiers.length > 1 ? "s" : ""}`);
    envoyer.mutate(
      { texte: contenu, fichiers },
      {
        onSuccess: () => {
          setTexte("");
          setFichiers([]);
        },
        onError: (e) =>
          toast.error(e instanceof ApiError ? e.message : "L'assistant n'a pas répondu. Réessayez."),
        onSettled: () => setEnAttente(null),
      },
    );
  }

  const vide = messages.length === 0 && !enAttente;

  return (
    <>
      <div ref={fil} className="flex-1 overflow-y-auto px-4 py-4">
        {chargement ? (
          <div className="flex h-full items-center justify-center text-muted-foreground">
            <Loader2 className="size-5 animate-spin" />
          </div>
        ) : conversationId === null ? (
          <div className="flex h-full flex-col items-center justify-center gap-3 text-center">
            <p className="text-sm text-muted-foreground">Commencez une conversation.</p>
            <Button className="gap-1.5" onClick={onCreer} disabled={creation}>
              <Plus className="size-4" /> Nouvelle conversation
            </Button>
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            {vide && (
              <div className="flex flex-col gap-2.5 py-6">
                <p className="px-1 text-center text-[13px] leading-relaxed text-muted-foreground">
                  Décrivez ce que vous voulez faire, ou joignez un emploi du temps ou une liste d&apos;étudiants
                  (PDF, photo). L&apos;assistant proposera des actions à valider.
                </p>
                {SUGGESTIONS.map((s) => (
                  <button
                    key={s.titre}
                    type="button"
                    onClick={() => {
                      setTexte(s.texte);
                      if (s.fichier) saisie.current?.click();
                    }}
                    className="flex items-start gap-3 rounded-2xl border border-border bg-background px-3.5 py-3 text-left transition-colors hover:border-primary/40 hover:bg-primary/5"
                  >
                    <s.icon className="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>
                      <span className="block text-[13px] font-medium text-foreground">{s.titre}</span>
                      <span className="block text-[12px] text-muted-foreground">
                        {s.fichier ? "Joignez le fichier, précisez la salle" : "Écrivez-le simplement"}
                      </span>
                    </span>
                  </button>
                ))}
              </div>
            )}

            {messages.map((m, i) => (
              <Bulle key={i} message={m} />
            ))}

            {enAttente && (
              <>
                <Bulle message={{ role: "user", texte: enAttente }} />
                <div className="flex items-center gap-2 px-1 text-[13px] text-muted-foreground">
                  <Loader2 className="size-3.5 animate-spin" />
                  L&apos;assistant consulte les données et prépare ses propositions…
                </div>
              </>
            )}

            {conversationId !== null && <ActionsProposees conversationId={conversationId} actions={actions} />}
          </div>
        )}
      </div>

      {conversationId !== null && (
        <div className="border-t border-border p-3">
          {fichiers.length > 0 && (
            <ul className="mb-2 flex flex-wrap gap-1.5">
              {fichiers.map((f, i) => (
                <li
                  key={`${f.nom}-${i}`}
                  className="flex items-center gap-1.5 rounded-lg border border-border bg-background px-2 py-1 text-[12px]"
                >
                  {f.type === "application/pdf" ? <FileText className="size-3.5" /> : <ImageIcon className="size-3.5" />}
                  <span className="max-w-[160px] truncate">{f.nom}</span>
                  <button
                    type="button"
                    aria-label={`Retirer ${f.nom}`}
                    onClick={() => setFichiers((prev) => prev.filter((_, j) => j !== i))}
                    className="text-muted-foreground hover:text-foreground"
                  >
                    <X className="size-3" />
                  </button>
                </li>
              ))}
            </ul>
          )}
          <div className="flex items-end gap-2">
            <input
              ref={saisie}
              type="file"
              accept={typesFichiers.join(",")}
              multiple
              hidden
              onChange={(e) => {
                void joindre(e.target.files);
                e.target.value = "";
              }}
            />
            <Button
              variant="outline"
              size="icon"
              aria-label="Joindre un PDF ou une image"
              disabled={envoyer.isPending || fichiers.length >= 3}
              onClick={() => saisie.current?.click()}
            >
              <Paperclip className="size-4" />
            </Button>
            <Textarea
              value={texte}
              onChange={(e) => setTexte(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && !e.shiftKey) {
                  e.preventDefault();
                  soumettre();
                }
              }}
              placeholder="Écrivez à l'assistant… (Entrée pour envoyer, Maj+Entrée pour une nouvelle ligne)"
              rows={2}
              disabled={envoyer.isPending}
              className="max-h-40 min-h-10 flex-1 resize-none rounded-xl text-[13px]"
            />
            <Button
              size="icon"
              aria-label="Envoyer"
              disabled={envoyer.isPending || (!texte.trim() && fichiers.length === 0)}
              onClick={soumettre}
            >
              {envoyer.isPending ? <Loader2 className="size-4 animate-spin" /> : <SendHorizontal className="size-4" />}
            </Button>
          </div>
        </div>
      )}
    </>
  );
}

function Bulle({ message }: { message: MessageIA }) {
  const admin = message.role === "user";
  return (
    <div className={cn("flex", admin ? "justify-end" : "justify-start")}>
      <div
        className={cn(
          "max-w-[88%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-[13px] leading-relaxed",
          admin ? "rounded-br-md bg-primary text-primary-foreground" : "rounded-bl-md bg-muted text-foreground",
        )}
      >
        {message.texte}
      </div>
    </div>
  );
}
