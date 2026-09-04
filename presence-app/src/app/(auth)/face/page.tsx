"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AlertCircle, Camera, Loader2, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useLogout, useMe } from "@/hooks/use-auth";
import { useEnrollFace, useVerifyFace } from "@/hooks/use-face-auth";
import { useFaceCamera } from "@/hooks/use-face-camera";
import { captureFaceDescriptor, loadFaceModels } from "@/lib/face-recognition";
import { getToken } from "@/lib/api-client";
import { cn } from "@/lib/utils";

/**
 * Seconde étape de connexion (Étudiants uniquement) : inscription du
 * visage à la toute première connexion, vérification aux suivantes. Le
 * jeton "en attente" émis par /api/auth/login|register n'ouvre l'accès à
 * aucune route métier tant que cette étape n'a pas réussi — voir
 * EnsureFaceVerified côté API.
 */
export default function FacePage() {
  const router = useRouter();
  const { data, isLoading, isError } = useMe();
  const { videoRef, status: cameraStatus, errorMessage: cameraErrorMessage, start: startCamera } =
    useFaceCamera();
  const enroll = useEnrollFace();
  const verify = useVerifyFace();
  const logout = useLogout();

  const [modelsReady, setModelsReady] = useState(false);
  const [captureError, setCaptureError] = useState<string | null>(null);
  const [isDetecting, setIsDetecting] = useState(false);

  const isFirstTime = !data?.face_enrolled;
  const mutation = isFirstTime ? enroll : verify;

  useEffect(() => {
    if (!getToken()) {
      router.replace("/login");
      return;
    }
    if (isError) {
      router.replace("/login");
      return;
    }
    // Jeton déjà complet (étape déjà passée) : rien à faire ici.
    if (!isLoading && data && !data.face_pending) {
      router.replace("/dashboard");
    }
  }, [isLoading, isError, data, router]);

  useEffect(() => {
    loadFaceModels().then(() => setModelsReady(true));
    startCamera();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleCapture() {
    const video = videoRef.current;
    if (!video) return;

    setCaptureError(null);
    setIsDetecting(true);

    try {
      const result = await captureFaceDescriptor(video);

      if (!result.ok) {
        setCaptureError(
          result.reason === "no-face"
            ? "Aucun visage détecté. Centrez votre visage dans le cadre, avec un bon éclairage."
            : result.reason === "multiple-faces"
              ? "Un seul visage doit être visible dans le cadre."
              : "La caméra n'est pas encore prête, patientez une seconde et réessayez.",
        );
        return;
      }

      mutation.mutate(
        { descriptor: result.descriptor },
        { onSuccess: () => router.replace("/dashboard") },
      );
    } finally {
      setIsDetecting(false);
    }
  }

  if (isLoading || !data) {
    return (
      <div className="flex flex-1 items-center justify-center py-16">
        <Loader2 className="h-6 w-6 animate-spin text-indigo-500" />
      </div>
    );
  }

  const cameraReady = cameraStatus === "ready";
  const showRetry = cameraStatus === "error";
  const busy = isDetecting || mutation.isPending;
  const canCapture = cameraReady && modelsReady && !busy;

  return (
    <div className="flex flex-col items-center gap-6 text-center">
      <div>
        <h2 className="font-display text-2xl font-bold tracking-tight text-ink-900">
          {isFirstTime ? "Inscription faciale" : "Vérification faciale"}
        </h2>
        <p className="mt-1.5 text-[15px] text-ink-500">
          {isFirstTime
            ? "Dernière étape : enregistrez votre visage pour sécuriser votre compte."
            : "Confirmez votre identité pour accéder à votre espace."}
        </p>
      </div>

      <div
        className={cn(
          "relative flex aspect-square w-56 items-center justify-center overflow-hidden rounded-full bg-ink-900/5 shadow-[0_0_0_4px_rgba(99,102,241,.15)] transition-shadow",
          busy && "shadow-[0_0_0_4px_rgba(99,102,241,.4)]",
        )}
      >
        <video
          ref={videoRef}
          muted
          playsInline
          className="h-full w-full scale-x-[-1] object-cover"
        />
        {(!cameraReady || !modelsReady) && !showRetry && (
          <div className="absolute inset-0 flex items-center justify-center bg-background/80">
            <Loader2 className="h-6 w-6 animate-spin text-indigo-500" />
          </div>
        )}
      </div>

      {(cameraErrorMessage || captureError) && (
        <div className="flex items-start gap-2 rounded-xl bg-destructive/10 px-3.5 py-3 text-left text-sm text-destructive">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{cameraErrorMessage ?? captureError}</span>
        </div>
      )}

      <Button
        onClick={showRetry ? startCamera : handleCapture}
        disabled={busy || (!showRetry && !canCapture)}
        className="h-12 w-full max-w-xs rounded-xl text-base font-medium shadow-sm"
      >
        {busy ? (
          "Analyse en cours…"
        ) : showRetry ? (
          "Réessayer"
        ) : !modelsReady || !cameraReady ? (
          "Chargement…"
        ) : (
          <>
            <Camera className="h-4 w-4" /> Prendre la photo
          </>
        )}
      </Button>

      <p className="flex items-center gap-1.5 text-xs text-ink-400">
        <ShieldCheck className="h-3.5 w-3.5 shrink-0" />
        Traité entièrement sur votre appareil, jamais envoyé sous forme d&apos;image.
      </p>

      <button
        type="button"
        onClick={() => logout.mutate(undefined, { onSettled: () => router.replace("/login") })}
        className="text-sm text-ink-400 underline-offset-2 hover:underline"
      >
        Ce n&apos;est pas vous ? Se déconnecter
      </button>
    </div>
  );
}
