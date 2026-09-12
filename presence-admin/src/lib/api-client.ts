const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8001";
const TOKEN_KEY = "presence_admin_token";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null): void {
  if (typeof window === "undefined") return;
  if (token) window.localStorage.setItem(TOKEN_KEY, token);
  else window.localStorage.removeItem(TOKEN_KEY);
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
  }
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken();

  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body instanceof FormData
        ? {}
        : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });

  if (res.status === 204) return undefined as T;

  const data = await res.json().catch(() => null);

  // La session d'administration expire désormais (voir
  // AuthController::expirationJeton). Sans ce traitement, un admin resté
  // ouvert toute la journée verrait un « Unauthenticated. » en anglais au
  // moment d'enregistrer, sans comprendre qu'il doit simplement se
  // reconnecter — et son jeton périmé resterait stocké.
  if (res.status === 401) {
    // On se contente d'effacer le jeton périmé : la redirection est du
    // ressort de l'interface, elle est centralisée dans query-provider.
    setToken(null);

    throw new ApiError("Votre session a expiré. Reconnectez-vous.", 401);
  }

  if (res.status === 429) {
    throw new ApiError(
      "Trop de tentatives en peu de temps. Patientez une minute avant de réessayer.",
      429,
    );
  }

  if (!res.ok) {
    throw new ApiError(
      data?.message ?? "Une erreur est survenue.",
      res.status,
      data?.errors,
    );
  }

  return data as T;
}

/**
 * Télécharge un fichier servi par l'API. Un simple lien ne suffit pas : la
 * requête doit porter le jeton, et le navigateur n'ajoute pas d'en-tête
 * Authorization à une navigation.
 */
export async function telechargerFichier(path: string, nomParDefaut: string): Promise<void> {
  const token = getToken();
  const res = await fetch(`${API_URL}${path}`, {
    headers: { Accept: "application/pdf", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => null);
    throw new ApiError(data?.message ?? "Le téléchargement a échoué.", res.status, data?.errors);
  }

  const nom =
    res.headers.get("Content-Disposition")?.match(/filename="?([^";]+)"?/)?.[1] ?? nomParDefaut;
  const url = URL.createObjectURL(await res.blob());
  const lien = document.createElement("a");
  lien.href = url;
  lien.download = nom;
  lien.click();
  URL.revokeObjectURL(url);
}
