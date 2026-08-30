export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-1 flex-col items-center justify-center bg-zinc-950 px-4">
      <div className="mb-8 flex flex-col items-center gap-1 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-600 text-xl text-white">
          ⚙️
        </div>
        <h1 className="text-xl font-semibold text-white">Présence — Administration</h1>
      </div>
      <div className="w-full max-w-sm">{children}</div>
    </div>
  );
}
