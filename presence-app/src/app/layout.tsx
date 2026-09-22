import type { Metadata, Viewport } from "next";
import { Atkinson_Hyperlegible_Next, Lora } from "next/font/google";
import { QueryProvider } from "@/lib/query-provider";
import { ThemeProvider } from "@/components/theme-provider";
import { AppToaster } from "@/components/app-toaster";
import "./globals.css";

// Deux voix humaines plutôt que les grotesques géométriques que tout le
// monde reconnaît : Lora, une serif aux racines calligraphiques, pour ce
// qui s'adresse à la personne (bonjour, titres) ; Atkinson Hyperlegible
// Next, dessinée pour rester lisible aux plus petites tailles, pour tout
// le reste. Chargées en police variable : un seul fichier par famille.
const display = Lora({
  variable: "--font-display",
  subsets: ["latin"],
  display: "swap",
});

const body = Atkinson_Hyperlegible_Next({
  variable: "--font-body",
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  title: "Ziris",
  description: "Ziris, votre espace de présence et de vie sur le campus.",
  icons: { icon: "/ziris.svg" },
  manifest: "/manifest.webmanifest",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#0f1828",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="fr"
      suppressHydrationWarning
      className={`${display.variable} ${body.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">
        <ThemeProvider>
          <QueryProvider>{children}</QueryProvider>
          <AppToaster />
        </ThemeProvider>
      </body>
    </html>
  );
}
