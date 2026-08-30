"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useRegister } from "@/hooks/use-auth";
import { useFilieres, useNiveaux, useSalles } from "@/hooks/use-catalog";
import { ApiError } from "@/lib/api-client";
import type { RegisterInput } from "@/hooks/use-auth";

type Role = RegisterInput["role"];

const inputClass = "border-violet-700/50 bg-white/10 text-white placeholder:text-violet-300";

const ROLES: { value: Role; label: string }[] = [
  { value: "Etudiant", label: "Étudiant" },
  { value: "Delegue", label: "Délégué de classe" },
  { value: "Enseignant", label: "Enseignant" },
];

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

  const errorMessage =
    register.error instanceof ApiError ? register.error.message : null;
  const fieldErrors = register.error instanceof ApiError ? register.error.errors : undefined;

  return (
    <Card className="border-violet-800/40 bg-white/5 backdrop-blur">
      <CardHeader>
        <CardTitle className="text-white">Inscription</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {errorMessage && (
            <Alert variant="destructive">
              <AlertDescription>{errorMessage}</AlertDescription>
            </Alert>
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

          <Field label="Téléphone" htmlFor="phone" error={fieldErrors?.phone?.[0]}>
            <Input
              id="phone"
              type="tel"
              required
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
            <select
              id="role"
              value={role}
              onChange={(e) => setRole(e.target.value as Role)}
              className="h-9 rounded-md border border-violet-700/50 bg-white/10 px-3 text-sm text-white"
            >
              {ROLES.map((r) => (
                <option key={r.value} value={r.value} className="text-black">
                  {r.label}
                </option>
              ))}
            </select>
          </Field>

          {needsAcademicFields && (
            <>
              <Field label="Niveau" htmlFor="niveau">
                <select
                  id="niveau"
                  required
                  value={niveauId ?? ""}
                  onChange={(e) => {
                    setNiveauId(Number(e.target.value) || undefined);
                    setFiliereId(undefined);
                    setSalleId(undefined);
                  }}
                  className="h-9 rounded-md border border-violet-700/50 bg-white/10 px-3 text-sm text-white"
                >
                  <option value="" className="text-black">
                    Choisir…
                  </option>
                  {niveaux?.map((n) => (
                    <option key={n.id} value={n.id} className="text-black">
                      {n.nom}
                    </option>
                  ))}
                </select>
              </Field>

              <Field label="Filière" htmlFor="filiere">
                <select
                  id="filiere"
                  required
                  disabled={!niveauId}
                  value={filiereId ?? ""}
                  onChange={(e) => {
                    setFiliereId(Number(e.target.value) || undefined);
                    setSalleId(undefined);
                  }}
                  className="h-9 rounded-md border border-violet-700/50 bg-white/10 px-3 text-sm text-white disabled:opacity-50"
                >
                  <option value="" className="text-black">
                    Choisir…
                  </option>
                  {filieres?.map((f) => (
                    <option key={f.id} value={f.id} className="text-black">
                      {f.nom}
                    </option>
                  ))}
                </select>
              </Field>

              <Field label="Salle" htmlFor="salle" error={fieldErrors?.salle_id?.[0]}>
                <select
                  id="salle"
                  required
                  disabled={!filiereId}
                  value={salleId ?? ""}
                  onChange={(e) => setSalleId(Number(e.target.value) || undefined)}
                  className="h-9 rounded-md border border-violet-700/50 bg-white/10 px-3 text-sm text-white disabled:opacity-50"
                >
                  <option value="" className="text-black">
                    Choisir…
                  </option>
                  {salles?.map((s) => (
                    <option key={s.id} value={s.id} className="text-black">
                      {s.nom} ({s.formation})
                    </option>
                  ))}
                </select>
              </Field>

              <Field label="Formation" htmlFor="formation">
                <select
                  id="formation"
                  value={formation}
                  onChange={(e) => setFormation(e.target.value)}
                  className="h-9 rounded-md border border-violet-700/50 bg-white/10 px-3 text-sm text-white"
                >
                  <option value="FI" className="text-black">
                    Formation Initiale
                  </option>
                  <option value="FA" className="text-black">
                    Formation Alternance
                  </option>
                  {role === "Etudiant" && (
                    <option value="FM" className="text-black">
                      Formation Migrante
                    </option>
                  )}
                </select>
              </Field>
            </>
          )}

          <Button type="submit" disabled={register.isPending} className="mt-2">
            {register.isPending ? "Inscription…" : "S'inscrire"}
          </Button>

          <p className="text-center text-sm text-violet-200">
            Déjà inscrit ?{" "}
            <Link href="/login" className="font-medium text-white underline">
              Se connecter
            </Link>
          </p>
        </form>
      </CardContent>
    </Card>
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
    <div className="flex flex-col gap-2">
      <Label htmlFor={htmlFor} className="text-violet-100">
        {label}
      </Label>
      {children}
      {error && <p className="text-xs text-red-300">{error}</p>}
    </div>
  );
}
