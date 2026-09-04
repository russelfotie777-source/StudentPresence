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

export type FaceCaptureResult =
  | { ok: true; descriptor: number[] }
  | { ok: false; reason: "no-face" | "multiple-faces" };

/**
 * Détecte explicitement TOUTES les faces (pas juste la première) pour
 * pouvoir refuser le cas "plusieurs visages" plutôt que de choisir
 * silencieusement un candidat — évite qu'une seconde personne dans le cadre
 * ne fausse l'inscription/vérification.
 */
export async function captureFaceDescriptor(
  video: HTMLVideoElement,
): Promise<FaceCaptureResult> {
  const faceapi = await getFaceApi();

  const detections = await faceapi
    .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
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
