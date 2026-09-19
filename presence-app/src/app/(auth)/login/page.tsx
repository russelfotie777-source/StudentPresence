"use client";

import { useRef, useState } from "react";
import Link from "next/link";
import Image from "next/image";
import dynamic from "next/dynamic";
import { useRouter } from "next/navigation";
import { motion, MotionConfig, useReducedMotion } from "motion/react";
import {
  AlertCircle,
  ArrowRight,
  ArrowUpRight,
  CheckCheck,
  Eye,
  EyeOff,
  Fingerprint,
  LockKeyhole,
  Pause,
  Play,
  LoaderCircle,
  UserRound,
} from "lucide-react";
import { useLogin } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import styles from "./login.module.css";
import { ZirisMark, ZirisWordmark } from "@/components/ziris-brand";

const LoginSculpture = dynamic(
  () =>
    import("@/components/login-sculpture").then(
      (module) => module.LoginSculpture,
    ),
  { ssr: false },
);

export default function LoginPage() {
  const router = useRouter();
  const login = useLogin();
  const reducedMotion = useReducedMotion();
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [visible, setVisible] = useState(false);
  const [capsLock, setCapsLock] = useState(false);
  const [paused, setPaused] = useState(false);
  const [focused, setFocused] = useState(false);
  const errorRef = useRef<HTMLDivElement>(null);
  const busy = login.isPending || login.isSuccess;

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (busy) return;
    login.mutate(
      { phone, password },
      {
        onSuccess: (data) => {
          if (data.requires_face) router.replace("/face");
          else if (
            data.user.role !== "Etudiant" &&
            data.user.validation_status !== "approved"
          )
            router.replace("/validation-en-attente");
          else router.replace("/dashboard");
        },
        onError: () => requestAnimationFrame(() => errorRef.current?.focus()),
      },
    );
  }
  const errorMessage =
    login.error instanceof ApiError
      ? (login.error.errors?.phone?.[0] ??
        login.error.errors?.password?.[0] ??
        login.error.message)
      : login.error
        ? "La connexion n’a pas abouti. Vérifiez votre connexion internet et réessayez."
        : null;

  return (
    <MotionConfig reducedMotion="user">
      <main className={styles.page}>
        <header className={styles.header}>
          <Link
            href="/"
            className={styles.wordmark}
            aria-label="Ziris, accueil"
          >
            <span>
              <ZirisMark size={23} />
            </span>
            <ZirisWordmark />
          </Link>
          <div className={styles.headerRight}>
            <Link href="/register">
              Créer un compte <ArrowUpRight size={15} />
            </Link>
          </div>
        </header>

        <section className={styles.visual} aria-label="Ziris">
          <div className={styles.scene}>
            <div className={styles.fallback} aria-hidden="true">
              <CheckCheck size={150} strokeWidth={1.3} />
            </div>
            <LoginSculpture
              paused={paused || !!reducedMotion || focused || busy}
            />
          </div>
          <motion.div
            className={styles.visualCopy}
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.15 }}
          >
            <div className={styles.visualTitle}>
              <h1>
                <ZirisWordmark />
              </h1>
            </div>
          </motion.div>
          <div className={styles.visualBottom}>
            <button
              type="button"
              onClick={() => setPaused(!paused)}
              aria-pressed={paused}
              aria-label={
                paused ? "Reprendre l’animation" : "Mettre l’animation en pause"
              }
              title={
                paused ? "Reprendre l’animation" : "Mettre l’animation en pause"
              }
            >
              {paused ? <Play size={15} /> : <Pause size={15} />}
            </button>
          </div>
        </section>

        <section className={styles.formSection} aria-labelledby="login-title">
          <motion.div
            className={styles.formInner}
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.1 }}
          >
            <span className={styles.formSymbol}>
              <Fingerprint size={25} strokeWidth={1.5} />
            </span>
            <h2 id="login-title">
              Bon retour <br />
              parmi nous<span>.</span>
            </h2>

            <form
              onSubmit={handleSubmit}
              onFocusCapture={() => setFocused(true)}
              onBlurCapture={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget))
                  setFocused(false);
              }}
              aria-busy={busy}
            >
              {errorMessage && (
                <div
                  className={styles.error}
                  role="alert"
                  tabIndex={-1}
                  ref={errorRef}
                  id="login-error"
                >
                  <AlertCircle size={17} />
                  <span>{errorMessage}</span>
                </div>
              )}
              <fieldset disabled={busy}>
                <div className={styles.field}>
                  <label htmlFor="phone">Matricule</label>
                  <div className={styles.inputWrap}>
                    <UserRound size={17} />
                    <input
                      id="phone"
                      name="phone"
                      type="text"
                      autoComplete="username"
                      autoCapitalize="none"
                      spellCheck={false}
                      required
                      placeholder="Votre matricule"
                      value={phone}
                      onChange={(event) => setPhone(event.target.value)}
                      aria-describedby={
                        errorMessage ? "login-error" : undefined
                      }
                    />
                  </div>
                </div>
                <div className={styles.field}>
                  <label htmlFor="password">Mot de passe</label>
                  <div className={styles.inputWrap}>
                    <LockKeyhole size={17} />
                    <input
                      id="password"
                      name="password"
                      type={visible ? "text" : "password"}
                      autoComplete="current-password"
                      required
                      placeholder="Votre mot de passe"
                      value={password}
                      onChange={(event) => setPassword(event.target.value)}
                      onKeyUp={(event) =>
                        setCapsLock(event.getModifierState("CapsLock"))
                      }
                      onKeyDown={(event) =>
                        setCapsLock(event.getModifierState("CapsLock"))
                      }
                      onBlur={() => setCapsLock(false)}
                      aria-describedby={capsLock ? "caps-lock" : undefined}
                    />
                    <button
                      type="button"
                      aria-label={
                        visible
                          ? "Masquer le mot de passe"
                          : "Afficher le mot de passe"
                      }
                      title={
                        visible
                          ? "Masquer le mot de passe"
                          : "Afficher le mot de passe"
                      }
                      aria-pressed={visible}
                      onClick={() => setVisible(!visible)}
                    >
                      {visible ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                  {capsLock && (
                    <p id="caps-lock" className={styles.capsLock}>
                      La touche majuscule est activée.
                    </p>
                  )}
                </div>
                <button type="submit" className={styles.submit} disabled={busy}>
                  <span>
                    {login.isSuccess
                      ? "Connexion réussie"
                      : login.isPending
                        ? "Connexion en cours…"
                        : "Se connecter"}
                  </span>
                  {busy ? (
                    <LoaderCircle size={19} className={styles.spinner} />
                  ) : (
                    <ArrowRight size={20} />
                  )}
                </button>
              </fieldset>
            </form>

            <div className={styles.signup}>
              <span>Pas encore de compte ?</span>
              <Link href="/register">
                Créer un compte <ArrowUpRight size={15} />
              </Link>
            </div>
          </motion.div>
          <footer className={styles.institution}>
            <Image src="/iut-douala.png" alt="" width={30} height={30} />
            <div>
              <strong>IUT de Douala</strong>
            </div>
            <span className={styles.institutionMark}><ZirisMark size={23} /></span>
          </footer>
        </section>
      </main>
    </MotionConfig>
  );
}
