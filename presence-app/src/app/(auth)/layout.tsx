import { OrbitHero } from "@/components/orbit-hero";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="relative flex min-h-screen flex-1 flex-col overflow-hidden bg-background">
      <OrbitHero heightClass="h-52" roundedClass="rounded-b-[2rem]" />

      <div className="relative mx-auto flex w-full max-w-sm flex-1 flex-col px-6 pb-12">
        <div className="relative z-10 -mt-9 mb-8 flex flex-col items-center gap-3 text-center">
          <div className="flex h-16 w-16 items-center justify-center rounded-[18px] bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-[0_18px_34px_-12px_rgba(79,70,229,.55)] ring-4 ring-background">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
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
