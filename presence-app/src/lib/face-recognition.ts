const MODEL_URL = "/models";

let modelsPromise: Promise<void> | null = null;

/**
 * @vladmandic/face-api embarque TensorFlow.js, qui suppose un environnement
 * navigateur — un import statique en tête de fichier fait planter le rendu
 * serveur/RSC de la page (client component) qui l'utilise. L'import dynamique
 * garde ce module hors du rendu serveur ET hors du bundle client tant que
 * l'écran /face n'est pas atteint (jamais au chargement du reste de l'app).
 */
async function getFaceApi() {
  return import("@vladmandic/face-api");
}

/**
 * Charge les modèles TensorFlow.js (~6.7 Mo, dominés par le réseau de
 * reconnaissance) une seule fois, mise en cache par le navigateur ensuite.
 */
export function loadFaceModels(): Promise<void> {
  modelsPromise ??= getFaceApi().then((faceapi) =>
    Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(() => undefined),
  );

  return modelsPromise;
}

export interface FacePoint {
  x: number;
  y: number;
}

export interface FaceBox {
  x: number;
  y: number;
  width: number;
  height: number;
}

export interface LiveFaceHint {
  score: number;
  box: FaceBox;
  landmarks: FacePoint[];
}

/**
 * Réglages du suivi EN CONTINU (pendant que la caméra tourne, avant même
 * de cliquer) : volontairement plus léger que la capture finale
 * (inputSize réduit) puisqu'il ne sert qu'à guider le cadrage en temps
 * réel, pas à produire le descripteur envoyé au serveur.
 */
const LIVE_DETECTOR_OPTIONS = { inputSize: 224, scoreThreshold: 0.4 };

/**
 * Un seul visage suivi (le plus net) suffit pour le guide de cadrage —
 * la vérité sur "combien de visages" est de toute façon retranchée au
 * moment de la capture réelle via captureFaceDescriptor/detectAllFaces.
 */
export async function detectFaceLive(video: HTMLVideoElement): Promise<LiveFaceHint | null> {
  if (video.readyState < video.HAVE_CURRENT_DATA || video.videoWidth === 0) {
    return null;
  }

  const faceapi = await getFaceApi();

  const result = await faceapi
    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions(LIVE_DETECTOR_OPTIONS))
    .withFaceLandmarks(true);

  if (!result) return null;

  return {
    score: result.detection.score,
    box: {
      x: result.detection.box.x,
      y: result.detection.box.y,
      width: result.detection.box.width,
      height: result.detection.box.height,
    },
    landmarks: result.landmarks.positions.map((p) => ({ x: p.x, y: p.y })),
  };
}

export type FacePositionQuality = "none" | "poor" | "good";

/**
 * Juge si le visage suivi est bien cadré DANS LA ZONE RÉELLEMENT VISIBLE
 * du cercle de prévisualisation (object-cover recadre la vidéo en un
 * carré central de côté min(largeur, hauteur) — un visage hors de ce
 * carré est hors champ à l'écran même s'il existe dans la frame brute).
 * Sert à guider l'utilisateur AVANT qu'il ne déclenche la capture, avec
 * les mêmes critères qui rendent la détection fiable.
 */
export function assessFacePosition(
  hint: LiveFaceHint | null,
  video: HTMLVideoElement | null,
): { quality: FacePositionQuality; message: string } {
  if (!hint || !video || !video.videoWidth) {
    return { quality: "none", message: "Centrez votre visage dans le cadre." };
  }

  const visibleSize = Math.min(video.videoWidth, video.videoHeight);
  const cropOffsetX = (video.videoWidth - visibleSize) / 2;
  const cropOffsetY = (video.videoHeight - visibleSize) / 2;

  const centerX = hint.box.x + hint.box.width / 2;
  const centerY = hint.box.y + hint.box.height / 2;
  const relX = (centerX - cropOffsetX) / visibleSize;
  const relY = (centerY - cropOffsetY) / visibleSize;
  const sizeRatio = hint.box.width / visibleSize;

  if (sizeRatio >= 0.85) {
    return { quality: "poor", message: "Éloignez-vous un peu." };
  }
  if (sizeRatio <= 0.3) {
    return { quality: "poor", message: "Rapprochez-vous un peu." };
  }
  if (Math.abs(relX - 0.5) >= 0.18 || Math.abs(relY - 0.5) >= 0.18) {
    return { quality: "poor", message: "Centrez votre visage dans le cadre." };
  }
  if (hint.score < 0.5) {
    return { quality: "poor", message: "Cherchez un meilleur éclairage." };
  }

  return { quality: "good", message: "Parfait, prenez la photo !" };
}

export type FaceCaptureResult =
  | { ok: true; descriptor: number[] }
  | { ok: false; reason: "no-face" | "multiple-faces" | "not-ready" };

/**
 * Seuil de confiance volontairement abaissé sous le défaut de la librairie
 * (0.5) : en conditions réelles (éclairage imparfait, visage proche/grand
 * dans le cadre), le défaut rate des visages pourtant bien présents. 0.3
 * reste suffisant pour écarter le bruit — la comparaison du descripteur
 * côté serveur (seuil de distance) reste le vrai filtre d'identité, cette
 * étape ne fait que localiser un visage à mesurer.
 */
const DETECTOR_OPTIONS_SCORE_THRESHOLD = 0.3;

/**
 * Détecte explicitement TOUTES les faces (pas juste la première) pour
 * pouvoir refuser le cas "plusieurs visages" plutôt que de choisir
 * silencieusement un candidat — évite qu'une seconde personne dans le cadre
 * ne fausse l'inscription/vérification.
 */
export async function captureFaceDescriptor(
  video: HTMLVideoElement,
): Promise<FaceCaptureResult> {
  // Le flux peut techniquement être "prêt" côté hook caméra sans qu'une
  // première frame décodée soit encore disponible — évite de faire tourner
  // le détecteur sur une image vide/noire (rendu silencieusement en "aucun
  // visage détecté", trompeur pour l'utilisateur).
  if (video.readyState < video.HAVE_CURRENT_DATA || video.videoWidth === 0) {
    return { ok: false, reason: "not-ready" };
  }

  const faceapi = await getFaceApi();

  const detections = await faceapi
    .detectAllFaces(
      video,
      new faceapi.TinyFaceDetectorOptions({ scoreThreshold: DETECTOR_OPTIONS_SCORE_THRESHOLD }),
    )
    .withFaceLandmarks(true)
    .withFaceDescriptors();

  if (detections.length === 0) {
    return { ok: false, reason: "no-face" };
  }

  if (detections.length > 1) {
    return { ok: false, reason: "multiple-faces" };
  }

  return { ok: true, descriptor: Array.from(detections[0].descriptor) };
}
