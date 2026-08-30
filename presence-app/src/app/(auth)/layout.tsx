export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="relative flex min-h-screen flex-1 flex-col overflow-hidden bg-background">
      <div
        aria-hidden
        className="pointer-events-none absolute -top-32 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-primary/25 blur-[100px]"
      />
      <div className="relative mx-auto flex w-full max-w-sm flex-1 flex-col justify-center px-6 py-12">
        <div className="mb-10 flex flex-col items-center gap-3 text-center">
          <div className="flex h-16 w-16 items-center justify-center rounded-[22px] bg-primary text-3xl shadow-lg shadow-primary/25">
            <span className="-mt-0.5">✓</span>
          </div>
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Présence</h1>
            <p className="text-sm text-muted-foreground">Pointage de présence en ligne</p>
          </div>
        </div>
        {children}
      </div>
    </div>
  );
}
