export function ZirisMark({ size = 24 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <path d="M5 6h22v5L13 21h14v5H5v-5l14-10H5V6Z" fill="currentColor" />
      <path d="M5 14h6l-6 4v-4ZM27 18h-6l6-4v4Z" fill="currentColor" opacity=".45" />
    </svg>
  );
}

export function ZirisWordmark() {
  return <span className="ziris-wordmark">Z<span className="ziris-iris">i</span>ris</span>;
}
