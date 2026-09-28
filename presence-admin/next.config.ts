import type { NextConfig } from "next";

if (process.env.VERCEL && !process.env.NEXT_PUBLIC_API_URL) {
  throw new Error("NEXT_PUBLIC_API_URL doit être configurée sur Vercel.");
}

const nextConfig: NextConfig = {
  // En local, le client utilise directement http://localhost:8001. En
  // production, NEXT_PUBLIC_API_URL est obligatoire (validation ci-dessus).
};

export default nextConfig;
