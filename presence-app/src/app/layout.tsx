import type { Metadata, Viewport } from "next";
import { Fira_Sans } from "next/font/google";
import { QueryProvider } from "@/lib/query-provider";
import { ThemeProvider } from "@/components/theme-provider";
import { AppToaster } from "@/components/app-toaster";
import { InstallPrompt } from "@/components/install-prompt";
import "./globals.css";

// Une seule voix, celle de la maquette de référence : Fira Sans, dessinée
// par Mozilla pour l'écran. Le titre en gras, le sous-titre en léger, le
// texte en régulier — c'est la graisse qui fait la hiérarchie, pas un
// changement de famille. Deux chargements pour ne prendre que les graisses
// utiles à chaque rôle.
const display = Fira_Sans({
  variable: "--font-display",
  weight: ["300", "600", "700"],
  subsets: ["latin"],
  display: "swap",
});

const body = Fira_Sans({
  variable: "--font-body",
  weight: ["400", "500", "600"],
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  metadataBase: new URL("https://ziris.vercel.app"),
  applicationName: "Ziris",
  title: {
    default: "Ziris — La présence, simplement",
    template: "%s · Ziris",
  },
  description:
    "Pointage, emploi du temps et suivi de présence réunis dans votre espace campus.",
  alternates: { canonical: "/" },
  openGraph: {
    type: "website",
    locale: "fr_FR",
    url: "/",
    siteName: "Ziris",
    title: "Ziris — La présence, simplement",
    description:
      "Pointage, emploi du temps et suivi de présence réunis dans votre espace campus.",
  },
  twitter: {
    card: "summary_large_image",
    title: "Ziris — La présence, simplement",
    description:
      "Pointage, emploi du temps et suivi de présence réunis dans votre espace campus.",
  },
  icons: {
    icon: "/ziris.svg",
    apple: "/ziris-192.png",
  },
  appleWebApp: {
    capable: true,
    title: "Ziris",
    statusBarStyle: "black-translucent",
  },
  formatDetection: { telephone: false },
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
          <InstallPrompt />
          <AppToaster />
        </ThemeProvider>
      </body>
    </html>
  );
}
