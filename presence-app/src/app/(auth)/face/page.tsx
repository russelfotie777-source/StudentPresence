"use client";

import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import dynamic from "next/dynamic";
import { useRouter } from "next/navigation";
import { motion, MotionConfig, useReducedMotion } from "motion/react";
import {
  AlertCircle,
  ArrowRight,
  Camera,
  Check,
  CheckCheck,
  LoaderCircle,
  LockKeyhole,
  LogOut,
  Pause,
  Play,
  RefreshCw,
  ScanFace,
  ShieldCheck,
} from "lucide-react";
import { ZirisMark, ZirisWordmark } from "@/components/ziris-brand";
import { useLogout, useMe } from "@/hooks/use-auth";
import { useEnrollFace, useVerifyFace } from "@/hooks/use-face-auth";
import { useFaceCamera } from "@/hooks/use-face-camera";
import { useLiveFaceTracking } from "@/hooks/use-live-face-tracking";
import {
  assessFacePosition,
  captureFaceDescriptor,
  loadFaceModels,
  type FacePositionQuality,
} from "@/lib/face-recognition";
import { ApiError, getToken } from "@/lib/api-client";
import type { IdentityLensStatus } from "@/components/identity-lens";
import styles from "./face.module.css";

const IdentityLens = dynamic(
  () =>
    import("@/components/identity-lens").then((module) => module.IdentityLens),
  { ssr: false },
);

const subscribeToHydration = () => () => {};

export default function FacePage() {
  const router = useRouter();
  const { data, isLoading, isError } = useMe();
  const {
    videoRef,
    status: cameraStatus,
    errorMessage: cameraError,
    start: startCamera,
    stop: stopCamera,
  } = useFaceCamera();
  const enroll = useEnrollFace();
  const verify = useVerifyFace();
  const logout = useLogout();
  const prefersReducedMotion = useReducedMotion();
  const hydrated = useSyncExternalStore(
    subscribeToHydration,
    () => true,
    () => false,
  );
  const reducedMotion = hydrated && prefersReducedMotion;
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const errorRef = useRef<HTMLDivElement>(null);
  const captureInFlight = useRef(false);
  const generation = useRef(0);
  const [modelsReady, setModelsReady] = useState(false);
  const [modelError, setModelError] = useState(false);
  const [captureError, setCaptureError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [verified, setVerified] = useState(false);
  const [paused, setPaused] = useState(false);
  const [position, setPosition] = useState<{
    quality: FacePositionQuality;
    message: string;
  }>({
    quality: "none",
    message: "Placez votre visage au centre de l’objectif.",
  });

  const isFirstTime = !data?.face_enrolled;
  const cameraReady = cameraStatus === "ready";
  const trackingActive =
    !!data?.face_pending && cameraReady && modelsReady && !busy && !verified;
  const liveHint = useLiveFaceTracking(videoRef, trackingActive);
  const errorMessage =
    captureError ??
    cameraError ??
    (modelError
      ? "Le moteur de vérification n’a pas pu charger. Vérifiez votre connexion et réessayez."
      : null);
  const canCapture =
    cameraReady && modelsReady && !busy && !verified && !logout.isPending;
  const retry = cameraStatus === "error" || modelError;
  const lensStatus: IdentityLensStatus = verified
    ? "success"
    : busy
      ? "verifying"
      : errorMessage
        ? "error"
        : !cameraReady || !modelsReady
          ? "loading"
          : position.quality === "good"
            ? "ready"
            : position.quality === "poor"
              ? "adjust"
              : "idle";
  const statusLabel = verified
    ? "Identité confirmée"
    : busy
      ? "Vérification en cours"
      : errorMessage
        ? "Une nouvelle tentative est nécessaire"
        : !cameraReady
          ? "Connexion à la caméra"
          : !modelsReady
            ? "Préparation de la vérification"
            : position.quality === "good"
              ? "Cadrage prêt"
              : position.quality === "poor"
                ? "Ajustez votre position"
                : "Placez-vous dans le cadre";

  useEffect(() => {
    if (!getToken() || isError) {
      router.replace("/login");
      return;
    }
    // `me` is refreshed before the mutation resolves; keep confirmation visible.
    if (
      !isLoading &&
      data &&
      !data.face_pending &&
      !captureInFlight.current &&
      !verified
    ) {
      router.replace("/dashboard");
    }
  }, [isLoading, isError, data, router, verified]);

  useEffect(() => {
    let active = true;
    const mountedGeneration = generation.current;
    loadFaceModels()
      .then(() => {
        if (active) setModelsReady(true);
      })
      .catch(() => {
        if (active) setModelError(true);
      });
    return () => {
      active = false;
      generation.current = mountedGeneration + 1;
    };
  }, []);

  useEffect(() => {
    if (data?.face_pending) startCamera();
  }, [data?.face_pending, startCamera]);

  useEffect(() => {
    if (!verified) return;
    const timer = setTimeout(
      () => router.replace("/dashboard"),
      reducedMotion ? 300 : 1300,
    );
    return () => clearTimeout(timer);
  }, [verified, reducedMotion, router]);

  useEffect(() => {
    setPosition(assessFacePosition(liveHint, videoRef.current));
  }, [liveHint, videoRef]);

  // Align real detection brackets with the mirrored, object-cover video.
  useEffect(() => {
    const canvas = canvasRef.current;
    const video = videoRef.current;
    if (!canvas) return;
    function draw() {
      if (!canvas) return;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = canvas.clientWidth * dpr;
      canvas.height = canvas.clientHeight * dpr;
      const ctx = canvas.getContext("2d");
      if (!ctx || !liveHint || !video?.videoWidth) return;
      const scale = Math.max(
        canvas.width / video.videoWidth,
        canvas.height / video.videoHeight,
      );
      const x =
        liveHint.box.x * scale + (canvas.width - video.videoWidth * scale) / 2;
      const y =
        liveHint.box.y * scale +
        (canvas.height - video.videoHeight * scale) / 2;
      const w = liveHint.box.width * scale;
      const h = liveHint.box.height * scale;
      const edge = Math.min(w / 5, 13 * dpr);
      ctx.strokeStyle = position.quality === "good" ? "#8ff7d0" : "#ffffff";
      ctx.lineWidth = 1.1 * dpr;
      ctx.shadowColor = "#183b32";
      ctx.shadowBlur = 3 * dpr;
      for (const [cx, cy, dx, dy] of [
        [x, y, 1, 1],
        [x + w, y, -1, 1],
        [x, y + h, 1, -1],
        [x + w, y + h, -1, -1],
      ]) {
        ctx.beginPath();
        ctx.moveTo(cx, cy + dy * edge);
        ctx.lineTo(cx, cy);
        ctx.lineTo(cx + dx * edge, cy);
        ctx.stroke();
      }
    }
    draw();
    const resize = new ResizeObserver(draw);
    resize.observe(canvas);
    return () => resize.disconnect();
  }, [liveHint, position.quality, videoRef]);

  async function handleCapture() {
    const video = videoRef.current;
    if (!video || !canCapture || captureInFlight.current) return;
    const attempt = generation.current;
    captureInFlight.current = true;
    setCaptureError(null);
    setBusy(true);
    try {
      const result = await captureFaceDescriptor(video);
      if (attempt !== generation.current) return;
      if (!result.ok) {
        setCaptureError(
          result.reason === "no-face"
            ? "Aucun visage détecté. Placez-vous face à la caméra, dans un endroit éclairé."
            : result.reason === "multiple-faces"
              ? "Un seul visage doit être visible dans l’objectif."
              : "La caméra n’est pas encore prête. Patientez un instant et réessayez.",
        );
        return;
      }
      await (isFirstTime ? enroll : verify).mutateAsync({
        descriptor: result.descriptor,
      });
      if (attempt !== generation.current) return;
      stopCamera();
      setVerified(true);
    } catch (error) {
      if (attempt !== generation.current) return;
      setCaptureError(
        error instanceof ApiError
          ? error.status === 429
            ? "Trop de tentatives. Patientez une minute avant de réessayer."
            : (error.errors?.descriptor?.[0] ?? error.message)
          : "La vérification n’a pas abouti. Vérifiez votre connexion et réessayez.",
      );
    } finally {
      captureInFlight.current = false;
      if (attempt === generation.current) setBusy(false);
    }
  }

  useEffect(() => {
    if (captureError) errorRef.current?.focus();
  }, [captureError]);

  function handleRetry() {
    setCaptureError(null);
    if (modelError) window.location.reload();
    else startCamera();
  }

  return (
    <MotionConfig reducedMotion="user">
      <main
        className={styles.page}
        data-status={lensStatus}
        data-motion={paused || reducedMotion ? "paused" : "active"}
      >
        <header className={styles.header}>
          <div className={styles.brand} aria-label="Ziris">
            <span>
              <ZirisMark size={22} />
            </span>
            <ZirisWordmark />
            <span className={styles.identityLabel}>Identité</span>
          </div>
          <div className={styles.headerRight}>
            <LockKeyhole size={13} />
            <span>Espace personnel</span>
          </div>
        </header>
        <div className={styles.content}>
          <motion.section
            className={styles.intro}
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.65 }}
          >
            <ol className={styles.steps} aria-label="Étapes de connexion">
              <li>
                <Check size={12} /> Connexion
              </li>
              <li aria-current="step">
                <span>02</span> Identité
              </li>
            </ol>
            <h1>
              {verified ? (
                <>
                  Identité
                  <br />
                  <em>confirmée.</em>
                </>
              ) : (
                <>
                  {isFirstTime ? "Votre première" : "Un regard."}
                  <br />
                  <em>{isFirstTime ? "empreinte." : "Et vous voilà."}</em>
                </>
              )}
            </h1>
            <p className={styles.description}>
              {verified
                ? "Votre espace est prêt. Nous vous y emmenons."
                : isFirstTime
                  ? "Enregistrez votre visage pour retrouver votre espace Ziris en toute sécurité."
                  : "Confirmez votre identité pour retrouver votre espace Ziris."}
            </p>
            <div className={styles.desktopIdentity}>
              <span className={styles.identityGlyph}>
                <ScanFace size={22} strokeWidth={1.25} />
              </span>
              <div>
                <span>Vérification faciale</span>
                <strong>
                  {isFirstTime && !verified
                    ? "Première connexion"
                    : "Votre accès personnel"}
                </strong>
              </div>
            </div>
            <div className={styles.assurance}>
              <ShieldCheck size={17} />
              <div>
                <strong>Votre visage reste le vôtre.</strong>
                <p>
                  Aucune photo n’est transmise. Seule une empreinte numérique du
                  visage est utilisée pour vérifier votre identité.
                </p>
              </div>
            </div>
          </motion.section>
          <section
            className={styles.experience}
            aria-label={
              isFirstTime ? "Inscription faciale" : "Vérification faciale"
            }
          >
            <motion.div
              className={styles.stage}
              initial={{ opacity: 0, scale: 0.93 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.9, delay: 0.1 }}
            >
              <div className={styles.stageLabel}>
                <span className={styles.liveDot} />{" "}
                {verified
                  ? "Confirmé"
                  : cameraReady
                    ? "Caméra active"
                    : "Caméra"}
              </div>
              <span className={styles.stageIndex} aria-hidden="true">
                Z / 02
              </span>
              <div className={styles.fallbackLens} aria-hidden="true" />
              <div className={styles.viewport}>
                <div className={styles.videoLayer}>
                  <video
                    ref={videoRef}
                    muted
                    playsInline
                    aria-label="Aperçu de votre caméra"
                  />
                  <canvas ref={canvasRef} aria-hidden="true" />
                </div>
                {(!cameraReady || !modelsReady || isLoading || !data) &&
                  !verified && (
                    <div className={styles.cameraPlaceholder}>
                      {retry ? (
                        <ScanFace size={44} strokeWidth={1} />
                      ) : (
                        <LoaderCircle
                          size={25}
                          strokeWidth={1.5}
                          className={styles.spinner}
                        />
                      )}
                      <span>
                        {isLoading || !data
                          ? "Chargement du compte"
                          : cameraStatus === "error"
                            ? "Caméra indisponible"
                            : modelError
                              ? "Analyse indisponible"
                              : !cameraReady
                                ? "Ouverture de la caméra"
                                : "Préparation de l’analyse"}
                      </span>
                    </div>
                  )}
                {busy && <div className={styles.scanLine} aria-hidden="true" />}
                {verified && (
                  <motion.div
                    className={styles.success}
                    initial={{ opacity: 0, scale: 0.88 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ duration: 0.4 }}
                  >
                    <CheckCheck size={64} strokeWidth={1.5} />
                    <span>Identité confirmée</span>
                  </motion.div>
                )}
              </div>
              <IdentityLens
                status={lensStatus}
                paused={paused || !!reducedMotion}
              />
              <div className={styles.opticsLabel} aria-hidden="true">
                <span>Ziris</span>
                <span>Identité · étape 2</span>
              </div>
              <button
                className={styles.motionToggle}
                type="button"
                onClick={() => setPaused((value) => !value)}
                disabled={!!reducedMotion}
                aria-pressed={paused || !!reducedMotion}
                aria-label={
                  paused
                    ? "Reprendre les animations"
                    : "Mettre les animations en pause"
                }
                title={
                  reducedMotion
                    ? "Animations réduites selon vos préférences"
                    : paused
                      ? "Reprendre les animations"
                      : "Mettre les animations en pause"
                }
              >
                {paused || reducedMotion ? (
                  <Play size={13} />
                ) : (
                  <Pause size={13} />
                )}
              </button>
            </motion.div>
            <div className={styles.controls}>
              <div
                className={styles.telemetry}
                aria-label="État de la vérification"
              >
                <div>
                  <span>01 · Caméra</span>
                  <strong>
                    {verified
                      ? "Terminée"
                      : cameraStatus === "error"
                        ? "Indisponible"
                        : cameraReady
                          ? "Connectée"
                          : "Connexion…"}
                  </strong>
                </div>
                <div>
                  <span>02 · Cadrage</span>
                  <strong>
                    {verified
                      ? "Confirmé"
                      : busy
                        ? "Analyse…"
                        : position.quality === "good"
                          ? "Prêt"
                          : "À ajuster"}
                  </strong>
                </div>
                <div>
                  <span>03 · Identité</span>
                  <strong>
                    {verified
                      ? "Vérifiée"
                      : busy
                        ? "Vérification…"
                        : "À confirmer"}
                  </strong>
                </div>
              </div>
              <div
                className={styles.feedback}
                aria-live="polite"
                aria-atomic="true"
              >
                {errorMessage ? (
                  <div
                    ref={errorRef}
                    tabIndex={-1}
                    role="alert"
                    className={styles.error}
                  >
                    <AlertCircle size={17} />
                    <span>{errorMessage}</span>
                  </div>
                ) : (
                  <>
                    <strong>{statusLabel}</strong>
                    <p>
                      {verified
                        ? "Bienvenue dans votre espace Ziris."
                        : busy
                          ? "Gardez votre visage dans le cadre."
                          : trackingActive
                            ? position.message
                            : "Cela peut prendre quelques instants."}
                    </p>
                  </>
                )}
              </div>
              <button
                className={styles.captureButton}
                type="button"
                onClick={
                  verified
                    ? () => router.replace("/dashboard")
                    : retry
                      ? handleRetry
                      : handleCapture
                }
                disabled={
                  !verified &&
                  (busy || logout.isPending || (!retry && !canCapture))
                }
              >
                <span className={styles.buttonIcon}>
                  {verified ? (
                    <Check size={19} />
                  ) : busy ? (
                    <LoaderCircle size={18} className={styles.spinner} />
                  ) : retry ? (
                    <RefreshCw size={18} />
                  ) : (
                    <Camera size={19} />
                  )}
                </span>
                <span>
                  {verified
                    ? "Accéder à mon espace"
                    : busy
                      ? "Vérification en cours…"
                      : retry
                        ? "Réessayer"
                        : !canCapture
                          ? "Préparation…"
                          : isFirstTime
                            ? "Enregistrer mon visage"
                            : "Confirmer mon identité"}
                </span>
                <ArrowRight size={18} className={styles.buttonArrow} />
              </button>
              <p className={styles.privacy}>
                <ShieldCheck size={12} /> Aucune photo transmise.
              </p>
            </div>
          </section>
        </div>
        <footer className={styles.footer}>
          <span className={styles.footerSignature}>
            Ziris<span>Votre campus, simplement.</span>
          </span>
          <button
            type="button"
            aria-label="Ce n’est pas vous ? Se déconnecter"
            disabled={busy || verified || logout.isPending}
            onClick={() =>
              logout.mutate(undefined, {
                onSettled: () => router.replace("/login"),
              })
            }
          >
            <LogOut size={13} />
            <span>
              {logout.isPending ? "Déconnexion…" : "Ce n’est pas vous ?"}
            </span>
          </button>
        </footer>
      </main>
    </MotionConfig>
  );
}
