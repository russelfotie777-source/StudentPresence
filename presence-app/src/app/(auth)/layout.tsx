export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-1 flex-col items-center justify-center bg-gradient-to-b from-violet-950 via-violet-900 to-black px-4 py-10">
      <div className="mb-8 flex flex-col items-center gap-2 text-center">
        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-500/20 text-2xl">
          🎓
        </div>
        <h1 className="text-2xl font-semibold text-white">Présence</h1>
        <p className="text-sm text-violet-200">Pointage de présence en ligne</p>
      </div>
      <div className="w-full max-w-sm">{children}</div>
    </div>
  );
}
