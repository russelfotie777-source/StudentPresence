"use client";

import {
  MutationCache,
  QueryCache,
  QueryClient,
  QueryClientProvider,
} from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError } from "@/lib/api-client";

export function QueryProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();

  const [client] = useState(() => {
    // La session d'administration expire (voir AuthController::expirationJeton).
    // Le jeton périmé est effacé dans api-client, mais la redirection appartient
    // à l'interface : la centraliser ici évite qu'un appel échoué laisse l'admin
    // sur un écran figé, quel que soit l'écran d'où venait l'appel.
    const versConnexion = (error: unknown) => {
      if (error instanceof ApiError && error.status === 401) {
        router.replace("/login?session=expiree");
      }
    };

    return new QueryClient({
      defaultOptions: {
        queries: {
          staleTime: 30_000,
          retry: 1,
        },
      },
      queryCache: new QueryCache({ onError: versConnexion }),
      mutationCache: new MutationCache({ onError: versConnexion }),
    });
  });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
