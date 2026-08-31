"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useRegister } from "@/hooks/use-auth";
import { useFilieres, useNiveaux, useSalles } from "@/hooks/use-catalog";
import { ApiError } from "@/lib/api-client";
import type { RegisterInput } from "@/hooks/use-auth";

type Role = RegisterInput["role"];

const ROLES: { value: Role; label: string }[] = [
  { value: "Etudiant", label: "Étudiant" },
  { value: "Delegue", label: "Délégué de classe" },
  { value: "Enseignant", label: "Enseignant" },
];

const inputClass = "h-12 rounded-xl text-base";
const triggerClass = "h-12 w-full rounded-xl text-base";

export default function RegisterPage() {
  const router = useRouter();
  const register = useRegister();

  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState<Role>("Etudiant");
  const [niveauId, setNiveauId] = useState<number | undefined>();
  const [filiereId, setFiliereId] = useState<number | undefined>();
  const [salleId, setSalleId] = useState<number | undefined>();
  const [formation, setFormation] = useState<string>("FI");

  const needsAcademicFields = role === "Etudiant" || role === "Delegue";

  const { data: niveaux } = useNiveaux();
  const { data: filieres } = useFilieres(niveauId);
  const { data: salles } = useSalles(filiereId);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    const input: RegisterInput = { name, phone, password, role };
    if (needsAcademicFields) {
      input.formation = formation;
      input.niveau_id = niveauId;
      input.filiere_id = filiereId;
      input.salle_id = salleId;
    }

    register.mutate(input, {
      onSuccess: (data) => {
        if (data.token) {
          router.replace("/dashboard");
        } else {
          router.replace("/login?inscrit=1");
        }
      },
    });
  }

  const errorMessage = register.error instanceof ApiError ? register.error.message : null;
  const fieldErrors = register.error instanceof ApiError ? register.error.errors : undefined;

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      <div>
        <h2 className="font-display text-2xl font-bold tracking-tight text-ink-900">
          Créer un compte
        </h2>
        <p className="mt-1.5 text-[15px] text-ink-500">
          Renseignez vos informations pour commencer.
        </p>
      </div>

      {errorMessage && (
        <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      <Field label="Nom complet" htmlFor="name">
        <Input
          id="name"
          required
          value={name}
          onChange={(e) => setName(e.target.value)}
          className={inputClass}
        />
      </Field>

      <Field
        label={role === "Etudiant" ? "Matricule" : "Téléphone"}
        htmlFor="phone"
        error={fieldErrors?.phone?.[0]}
      >
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

          <Field label="Salle" htmlFor="salle" error={fieldErrors?.salle_id?.[0]}>
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

      <Button
        type="submit"
        disabled={register.isPending}
        className="mt-1 h-12 rounded-xl text-base font-medium shadow-sm"
      >
        {register.isPending ? "Inscription…" : "S'inscrire"}
      </Button>

      <p className="text-center text-sm text-muted-foreground">
        Déjà inscrit ?{" "}
        <Link href="/login" className="font-medium text-primary">
          Se connecter
        </Link>
      </p>
    </form>
  );
}

function Field({
  label,
  htmlFor,
  error,
  children,
}: {
  label: string;
  htmlFor: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={htmlFor}>{label}</Label>
      {children}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}
