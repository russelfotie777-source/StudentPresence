"use client";

import { Suspense, useEffect, useState } from "react";
import { Eye, EyeOff, ArrowRight, AlertCircle, Info, Loader2 } from "lucide-react";
import { motion } from "motion/react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useLogin, useMe } from "@/hooks/use-auth";
import { getToken } from "@/lib/api-client";

export default function LoginPage() {
  return (
    <Suspense fallback={null}>
      <LoginForm />
    </Suspense>
  );
}

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  // Renseigné par api-client lorsqu'un appel se heurte à un jeton périmé :
  // sans cette explication, l'admin se retrouverait sur l'écran de connexion
  // sans savoir pourquoi il a été éjecté.
  const sessionExpiree = searchParams.get("session") === "expiree";
  // Page réellement demandée avant l'éjection : y revenir évite de faire
  // renaviguer l'admin à la main après chaque expiration de session.
  const retour = searchParams.get("retour");
  const login = useLogin();
  // Revenir en arrière après s'être connecté, ou rouvrir un onglet sur
  // /login, affichait un formulaire de connexion à quelqu'un qui l'est déjà.
  const { data: session } = useMe();
  const dejaConnecte = !!getToken() && session?.user.role === "Admin";
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [motDePasseVisible, setMotDePasseVisible] = useState(false);

  useEffect(() => {
    if (dejaConnecte) {
      router.replace("/dashboard");
    }
  }, [dejaConnecte, router]);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    login.mutate(
      { phone, password },
      {
        // Une destination doit rester interne : une valeur venue de l'URL ne
        // doit pas pouvoir servir à rediriger vers un site tiers après une
        // connexion réussie.
        onSuccess: () =>
          router.replace(retour?.startsWith("/") && !retour.startsWith("//") ? retour : "/dashboard"),
      },
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
      className="flex flex-col gap-8"
    >
      <div className="flex flex-col gap-1.5 lg:hidden">
        <div className="mb-2 flex items-center gap-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">
            P
          </div>
          <span className="font-display text-sm font-semibold text-foreground">Présence</span>
        </div>
      </div>

      <div>
        <h2 className="font-display text-2xl font-semibold tracking-tight text-foreground">
          Connexion
        </h2>
        <p className="mt-1.5 text-[14.5px] text-muted-foreground">
          Accédez au back-office administrateur.
        </p>
      </div>

      {sessionExpiree && !login.error && (
        <div className="flex items-start gap-2.5 rounded-xl border border-border bg-muted/60 px-3.5 py-3 text-[13px] text-muted-foreground">
          <Info className="mt-0.5 size-4 shrink-0 text-primary" />
          <span>Votre session a expiré après 12 heures. Reconnectez-vous pour continuer.</span>
        </div>
      )}
      {login.error && (
        <div className="flex items-start gap-2.5 rounded-xl border border-destructive/25 bg-destructive/10 px-3.5 py-3 text-[13px] text-destructive">
          <AlertCircle className="mt-0.5 size-4 shrink-0" />
          <span>{login.error.message}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        <div className="flex flex-col gap-2">
          <Label htmlFor="phone">Téléphone</Label>
          <Input
            id="phone"
            type="tel"
            name="username"
            autoComplete="username"
            autoFocus
            required
            placeholder="6XX XXX XXX"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            className="h-11 rounded-xl px-3.5 text-[15px]"
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="password">Mot de passe</Label>
          <div className="relative">
            <Input
              id="password"
              type={motDePasseVisible ? "text" : "password"}
              name="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="h-11 rounded-xl px-3.5 pr-11 text-[15px]"
            />
            <button
              type="button"
              onClick={() => setMotDePasseVisible((v) => !v)}
              className="absolute right-0 top-0 flex h-full w-11 items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
              aria-label={motDePasseVisible ? "Masquer le mot de passe" : "Afficher le mot de passe"}
            >
              {motDePasseVisible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>

        <motion.div whileTap={{ scale: 0.98 }} transition={{ type: "spring", stiffness: 500, damping: 25 }}>
          <Button
            type="submit"
            disabled={login.isPending}
            className="h-11 w-full gap-1.5 rounded-xl text-[15px] font-semibold shadow-sm"
          >
            {login.isPending ? (
              <>
                <Loader2 className="size-4 animate-spin" />
                Connexion…
              </>
            ) : (
              <>
                Se connecter
                <ArrowRight className="size-4 transition-transform group-hover/button:translate-x-0.5" />
              </>
            )}
          </Button>
        </motion.div>
      </form>
    </motion.div>
  );
}
