import { GrainOverlay } from "@/components/grain-overlay";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="relative flex min-h-screen flex-1 flex-col overflow-hidden bg-background">
      <div
        aria-hidden
        className="pointer-events-none absolute -top-36 left-1/2 h-[340px] w-[340px] -translate-x-1/2 rounded-full bg-indigo-500/25 blur-[100px]"
      >
        <GrainOverlay className="rounded-full opacity-40" />
      </div>

      <svg
        aria-hidden
        className="pointer-events-none absolute top-5 right-5 opacity-50"
        width="86"
        height="70"
        viewBox="0 0 86 70"
        fill="none"
      >
        <circle cx="8" cy="10" r="2.5" fill="#655cea" />
        <circle cx="40" cy="4" r="2" fill="#655cea" />
        <circle cx="70" cy="18" r="3" fill="#4f46e5" />
        <circle cx="24" cy="34" r="2" fill="#b3aec7" />
        <circle cx="60" cy="46" r="2.5" fill="#655cea" />
        <circle cx="8" cy="52" r="2" fill="#b3aec7" />
        <path d="M8 10L40 4L70 18" stroke="#655cea" strokeWidth="1" strokeDasharray="2 3" opacity=".6" />
        <path d="M40 4L24 34L60 46" stroke="#655cea" strokeWidth="1" strokeDasharray="2 3" opacity=".6" />
      </svg>

      <div className="relative mx-auto flex w-full max-w-sm flex-1 flex-col justify-center px-6 py-12">
        <div className="mb-10 flex flex-col items-center gap-3 text-center">
          <div className="flex h-14 w-14 items-center justify-center rounded-[16px] bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-[0_18px_34px_-12px_rgba(79,70,229,.45)]">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path
                d="M4 12.5L9.5 18L20 6"
                stroke="white"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </div>
          <div>
            <h1 className="font-display text-xl font-bold tracking-tight text-ink-900">
              Présence
            </h1>
            <p className="text-sm text-ink-500">Pointage de présence en ligne</p>
          </div>
        </div>
        {children}
      </div>
    </div>
  );
}
