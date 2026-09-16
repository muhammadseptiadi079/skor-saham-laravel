import type { ReactNode } from 'react';

interface StatCardProps {
    label: string;
    value: number;
    icon: ReactNode;
    gradient: string;
    /** Tailwind shadow-color class (e.g. "shadow-cyan-900/40") — tints the drop shadow to match the gradient. */
    glow?: string;
    delayMs?: number;
    onClick?: () => void;
}

// Always a <button> (a no-op one when onClick is omitted) — used as a shortcut into the relevant
// tab, so keeping one element type avoids juggling two different prop shapes for div vs button.
export default function StatCard({ label, value, icon, gradient, glow = 'shadow-slate-900/20', delayMs = 0, onClick }: StatCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`animate-pop-in relative overflow-hidden rounded-2xl bg-gradient-to-br ${gradient} p-4 text-left text-white shadow-lg ${glow} ring-1 ring-white/15 ${onClick ? 'transition-transform hover:scale-[1.03] active:scale-95' : ''}`}
            style={{ animationDelay: `${delayMs}ms` }}
        >
            {/* Faint diagonal sheen for a glassier, less flat look */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-white/20 via-transparent to-black/10"
            />
            <div className="animate-breathe pointer-events-none absolute -top-3 -right-3 h-20 w-20">{icon}</div>
            <p className="relative font-display text-3xl font-bold tabular-nums">{value}</p>
            <p className="relative mt-1 text-xs font-medium text-white/85">{label}</p>
        </button>
    );
}
