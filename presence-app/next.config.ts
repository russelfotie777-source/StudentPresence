import type { NextConfig } from "next";

/*
 * Sans NEXT_PUBLIC_API_URL, l'app parle à l'API par son propre domaine
 * (/api/… est relayé vers le serveur Laravel) : un seul port à exposer,
 * un seul lien à partager quand on montre l'app depuis un tunnel. Avec la
 * variable, elle appelle l'API directement, comme en production.
 */
const API_LOCALE = process.env.API_PROXY ?? "http://localhost:8001";

const nextConfig: NextConfig = {
  async rewrites() {
    if (process.env.NEXT_PUBLIC_API_URL) return [];
    return [{ source: "/api/:path*", destination: `${API_LOCALE}/api/:path*` }];
  },
  // Les tunnels de démonstration ouvrent le serveur de dev sous un autre nom.
  allowedDevOrigins: ["*.trycloudflare.com", "*.ngrok-free.app", "*.ngrok.app", "*.loca.lt"],
};

export default nextConfig;
