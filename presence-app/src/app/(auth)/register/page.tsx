"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useFilieres, useNiveaux, useSalles } from "@/hooks/use-catalog";

type Role = "Etudiant" | "Delegue" | "Enseignant";

const ROLES: { value: Role; label: string }[] = [
  { value: "Etudiant", label: "Étudiant" },
  { value: "Delegue", label: "Délégué de classe" },
  { value: "Enseignant", label: "Enseignant" },
];

const inputClass = "h-12 rounded-xl text-base";
const triggerClass = "h-12 w-full rounded-xl text-base";

export default function RegisterPage() {
  const router = useRouter();

  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState<Role>("Etudiant");
  const [niveauId, setNiveauId] = useState<number | undefined>();
  const [filiereId, setFiliereId] = useState<number | undefined>();
  const [salleId, setSalleId] = useState<number | undefined>();
  const [formation, setFormation] = useState<string>("FI");
  const [registrationBlockedOpen, setRegistrationBlockedOpen] = useState(false);

  const needsAcademicFields = role === "Etudiant" || role === "Delegue";

  const { data: niveaux } = useNiveaux();
  const { data: filieres } = useFilieres(niveauId);
  const { data: salles } = useSalles(filiereId);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPassword("");
    setRegistrationBlockedOpen(true);
  }

  return (
    <>
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        <div>
          <h2 className="font-display text-2xl font-bold tracking-tight text-ink-900">
            Créer un compte
          </h2>
        </div>

        <Field label="Nom complet" htmlFor="name">
          <Input
            id="name"
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            className={inputClass}
          />
        </Field>

        <Field label={role === "Etudiant" ? "Matricule" : "Téléphone"} htmlFor="phone">
          <Input
            id="phone"
            type={role === "Etudiant" ? "text" : "tel"}
            autoComplete={role === "Etudiant" ? "off" : "tel"}
            required
            placeholder={role === "Etudiant" ? "24I01234" : "6XX XXX XXX"}
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            className={inputClass}
          />
        </Field>

        <Field label="Mot de passe" htmlFor="password">
          <Input
            id="password"
            type="password"
            required
            minLength={8}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className={inputClass}
          />
        </Field>

        <Field label="Rôle" htmlFor="role">
          <Select value={role} onValueChange={(v) => v && setRole(v as Role)}>
            <SelectTrigger className={triggerClass}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {ROLES.map((r) => (
                <SelectItem key={r.value} value={r.value}>
                  {r.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>

        {needsAcademicFields && (
          <>
            <Field label="Niveau" htmlFor="niveau">
              <Select
                value={niveauId ? String(niveauId) : ""}
                onValueChange={(v) => {
                  setNiveauId(v ? Number(v) : undefined);
                  setFiliereId(undefined);
                  setSalleId(undefined);
                }}
              >
                <SelectTrigger className={triggerClass}>
                  <SelectValue placeholder="Choisir…" />
                </SelectTrigger>
                <SelectContent>
                  {niveaux?.map((n) => (
                    <SelectItem key={n.id} value={String(n.id)}>
                      {n.nom}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field label="Filière" htmlFor="filiere">
              <Select
                value={filiereId ? String(filiereId) : ""}
                onValueChange={(v) => {
                  setFiliereId(v ? Number(v) : undefined);
                  setSalleId(undefined);
                }}
                disabled={!niveauId}
              >
                <SelectTrigger className={triggerClass}>
                  <SelectValue placeholder="Choisir…" />
                </SelectTrigger>
                <SelectContent>
                  {filieres?.map((f) => (
                    <SelectItem key={f.id} value={String(f.id)}>
                      {f.nom}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field label="Salle" htmlFor="salle">
              <Select
                value={salleId ? String(salleId) : ""}
                onValueChange={(v) => setSalleId(v ? Number(v) : undefined)}
                disabled={!filiereId}
              >
                <SelectTrigger className={triggerClass}>
                  <SelectValue placeholder="Choisir…" />
                </SelectTrigger>
                <SelectContent>
                  {salles?.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.nom} ({s.formation})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field label="Formation" htmlFor="formation">
              <Select value={formation} onValueChange={(v) => v && setFormation(v)}>
                <SelectTrigger className={triggerClass}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="FI">Formation Initiale</SelectItem>
                  <SelectItem value="FA">Formation Alternance</SelectItem>
                </SelectContent>
              </Select>
            </Field>
          </>
        )}

        <Button type="submit" className="mt-1 h-12 rounded-xl text-base font-medium shadow-sm">
          S&apos;inscrire
        </Button>

        <p className="text-center text-sm text-muted-foreground">
          Déjà inscrit ?{" "}
          <Link href="/login" className="font-medium text-primary">
            Se connecter
          </Link>
        </p>
      </form>

      <Dialog open={registrationBlockedOpen} onOpenChange={setRegistrationBlockedOpen}>
        <DialogContent className="rounded-xl">
          <DialogHeader>
            <DialogTitle>Inscriptions publiques fermées</DialogTitle>
            <DialogDescription className="leading-relaxed">
              Votre compte doit être préinscrit par l&apos;administration. Si vos identifiants
              vous ont déjà été transmis, vous pouvez vous connecter directement.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRegistrationBlockedOpen(false)}>
              Fermer
            </Button>
            <Button onClick={() => router.push("/login")}>Se connecter</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function Field({
  label,
  htmlFor,
  children,
}: {
  label: string;
  htmlFor: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={htmlFor}>{label}</Label>
      {children}
    </div>
  );
}
