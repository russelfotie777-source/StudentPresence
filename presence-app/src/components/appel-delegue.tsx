"use client";

import { useEffect, useRef } from "react";
import { Check, GraduationCap, MapPin, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useGeolocation, type Coords } from "@/hooks/use-geolocation";
import { usePermission } from "@/hooks/use-permission";
import { PermissionRefusee } from "@/components/demande-permission";
import {
  useConfirmerEnseignant,
  useMarkDelegue,
  useSendPosition,
} from "@/hooks/use-seances";
import type { Seance } from "@/types/api";

/*
 * L'appel, vu par le délégué : trois choses à faire pendant la séance, et
 * pour chacune, où elle en est — écrit en toutes lettres plutôt que deviné
 * d'après l'état d'un bouton. La salle à situer, l'enseignant à voir
 * arriver, la classe à confirmer. Ce qui est fait se coche et ne propose
 * plus rien ; ce qui reste à faire porte son bouton.
 */

function Ligne({
  icone,
  titre,
  etat,
  fait = false,
  children,
}: {
  icone: React.ReactNode;
  titre: string;
  etat: React.ReactNode;
  fait?: boolean;
  children?: React.ReactNode;
}) {
  return (
    <div className="appel-ligne" data-fait={fait || undefined}>
      <span className="appel-icone" aria-hidden>
        {fait ? <Check size={16} strokeWidth={2.5} /> : icone}
      </span>
      <div className="appel-texte">
        <strong>{titre}</strong>
        <span aria-live="polite">{etat}</span>
      </div>
      {children && <div className="appel-action">{children}</div>}
    </div>
  );
}

function LignePosition({ seance }: { seance: Seance }) {
  const plafond = seance.geolocation?.max_position_accuracy_meters;
  const geo = useGeolocation(plafond);
  const envoi = useSendPosition(seance.id);
  const { mutate: envoyer } = envoi;
  const soumis = useRef<Coords | null>(null);
  const { etat: permission } = usePermission("position");
  const refusee = geo.error === "permission_denied" || permission === "refusee";
  const envoyee = seance.position_envoyee === true || envoi.isSuccess;

  // La position part d'elle-même dès que le téléphone l'a trouvée : un
  // seul geste pour le délégué, pas deux.
  useEffect(() => {
    if (geo.status === "success" && geo.coords && !envoyee && soumis.current !== geo.coords) {
      soumis.current = geo.coords;
      envoyer(geo.coords);
    }
  }, [geo.status, geo.coords, envoyee, envoyer]);

  if (envoyee) {
    return (
      <Ligne
        icone={<MapPin size={16} />}
        titre="Salle située"
        fait
        etat={
          seance.position_envoyee_a
            ? `Position envoyée à ${seance.position_envoyee_a}. Les étudiants peuvent pointer.`
            : "Position envoyée. Les étudiants peuvent pointer."
        }
      />
    );
  }

  const cherche = geo.status === "loading" || envoi.isPending;
  const echec = geo.status === "error" || envoi.isError;

  const etat = refusee
    ? "Le navigateur n'a pas le droit de vous localiser."
    : cherche
      ? envoi.isPending
        ? "Envoi aux étudiants…"
        : geo.coords
          ? "Position trouvée, on affine…"
          : "Le téléphone cherche sa position…"
      : geo.status === "error"
        ? geo.errorMessage
        : envoi.isError
          ? "L'envoi n'a pas abouti. Réessayez."
          : "Elle ouvre le pointage aux étudiants.";

  return (
    <>
      <Ligne icone={<MapPin size={16} />} titre="Position de la salle" etat={etat}>
        <Button onClick={() => geo.locate()} disabled={cherche || refusee}>
          {cherche ? "Recherche…" : echec ? "Réessayer" : "Envoyer"}
        </Button>
      </Ligne>
      {refusee && (
        <div className="appel-encart">
          <PermissionRefusee type="position" />
        </div>
      )}
    </>
  );
}

function LigneEnseignant({ seance }: { seance: Seance }) {
  const marquer = useMarkDelegue(seance.id);
  const confirmer = useConfirmerEnseignant(seance.id);
  const figee = seance.presences_locked || marquer.isPending;
  const arrivee = seance.debut_reel?.slice(0, 5);
  const depart = seance.fin_reelle?.slice(0, 5);

  // Règle admin : quand l'enseignant n'utilise pas l'app, le délégué peut
  // confirmer sa présence à sa place — tant que l'enseignant n'a pas
  // répondu lui-même et que le délégué ne l'a pas marqué absent.
  const peutConfirmer =
    seance.confirmation_enseignant_par_delegue === true &&
    seance.etat_prof === null &&
    seance.etat_delegue === "present" &&
    !seance.presences_locked;

  // La fin réelle du cours conditionne la paie de l'enseignant : elle se
  // relève une fois le cours commencé, tant qu'elle n'est pas posée. Si le
  // délégué oublie, la séance est clôturée à l'heure prévue par le serveur.
  const peutTerminer =
    seance.etat_delegue === "present" && arrivee !== undefined && !depart && !seance.presences_locked;

  if (seance.etat_delegue === "present") {
    return (
      <Ligne
        icone={<GraduationCap size={16} />}
        titre={seance.enseignant}
        fait
        etat={
          <>
            {depart
              ? `Présent, de ${arrivee} à ${depart}.`
              : arrivee
                ? `Présent, arrivé à ${arrivee}.`
                : "Présent."}
            {seance.etat_prof_par_delegue && " Confirmé à sa place."}
            {peutConfirmer && (
              <>
                {" "}
                <button type="button" disabled={confirmer.isPending} onClick={() => confirmer.mutate()}>
                  Il n’a pas l’app ? Confirmer à sa place.
                </button>
              </>
            )}
          </>
        }
      >
        {peutTerminer && (
          <Button disabled={marquer.isPending} onClick={() => marquer.mutate({ etat: "present", set_fin_reelle: true })}>
            Noter la fin
          </Button>
        )}
      </Ligne>
    );
  }

  if (seance.etat_delegue === "absent") {
    return (
      <Ligne icone={<GraduationCap size={16} />} titre={seance.enseignant} etat="Marqué absent. S’il arrive, notez-le.">
        <Button disabled={figee} onClick={() => marquer.mutate({ etat: "present", set_debut_reel: true })}>
          Présent
        </Button>
      </Ligne>
    );
  }

  return (
    <Ligne icone={<GraduationCap size={16} />} titre={seance.enseignant} etat="Est-il en salle ?">
      <Button disabled={figee} onClick={() => marquer.mutate({ etat: "present", set_debut_reel: true })}>
        Présent
      </Button>
      <Button variant="ghost" className="appel-discret" disabled={figee} onClick={() => marquer.mutate({ etat: "absent" })}>
        Absent
      </Button>
    </Ligne>
  );
}

function LigneClasse({ seance }: { seance: Seance }) {
  const pointes = seance.pointes_count ?? 0;
  const annonces = seance.push?.etudiants_presents;

  if (seance.presences_locked) {
    return (
      <Ligne
        icone={<Users size={16} />}
        titre="La classe"
        fait
        etat={`Liste confirmée : ${pointes} présent${pointes > 1 ? "s" : ""}.`}
      />
    );
  }

  const compte =
    pointes === 0
      ? "Personne n’a encore pointé."
      : pointes === 1
        ? "Un étudiant a pointé."
        : `${pointes} étudiants ont pointé.`;
  const effectif =
    annonces !== undefined
      ? ` L’enseignant en compte ${annonces}.`
      : " L’enseignant n’a pas encore donné son effectif.";

  return <Ligne icone={<Users size={16} />} titre="La classe" etat={compte + effectif} />;
}

export function AppelDelegue({
  seance,
  onConfirmerListe,
}: {
  seance: Seance;
  onConfirmerListe: () => void;
}) {
  return (
    <div className="role-actions">
      <div className="appel">
        <LignePosition seance={seance} />
        <LigneEnseignant seance={seance} />
        <LigneClasse seance={seance} />
      </div>
      {!seance.presences_locked && (
        <Button className="checkin-primary" onClick={onConfirmerListe}>
          <Users size={17} /> Confirmer la liste
        </Button>
      )}
    </div>
  );
}
