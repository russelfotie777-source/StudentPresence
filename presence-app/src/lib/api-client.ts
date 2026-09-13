const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8001";
const TOKEN_KEY = "presence_token";

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

  // Le pointage, l'envoi de position et la connexion sont désormais limités
  // en débit. Laravel répond alors « Too Many Attempts. » en anglais : on le
  // traduit, et on dit combien de temps patienter quand le serveur l'indique.
  if (res.status === 429) {
    const secondes = Number(res.headers.get("Retry-After"));
    const attente =
      Number.isFinite(secondes) && secondes > 0
        ? `Réessayez dans ${secondes >= 60 ? "une minute" : `${secondes} s`}.`
        : "Patientez un instant avant de réessayer.";

    throw new ApiError(`Trop de tentatives. ${attente}`, 429);
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
