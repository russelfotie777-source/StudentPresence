"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";

export function QueryProvider({ children }: { children: React.ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            retry: 1,
            // Sur mobile, revenir sur l'app est un geste constant (notification,
            // changement d'app, écran qui se rallume) : refetcher toutes les
            // requêtes montées à chaque retour multiplie les appels réseau sur
            // des données qui bougent peu. Ce dont la fraîcheur compte
            // vraiment — les séances du jour — a son propre refetchInterval.
            refetchOnWindowFocus: false,
          },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
