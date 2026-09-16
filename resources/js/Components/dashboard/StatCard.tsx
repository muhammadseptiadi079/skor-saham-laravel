import type { ReactNode } from 'react';

interface StatCardProps {
    label: string;
    value: number;
    icon: ReactNode;
    gradient: string;
    delayMs?: number;
    onClick?: () => void;
}

// Always a <button> (a no-op one when onClick is omitted) — used as a shortcut into the relevant
// tab, so keeping one element type avoids juggling two different prop shapes for div vs button.
export default function StatCard({ label, value, icon, gradient, delayMs = 0, onClick }: StatCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`animate-pop-in relative overflow-hidden rounded-2xl bg-gradient-to-br ${gradient} p-4 text-left text-white shadow-lg ${onClick ? 'transition-transform hover:scale-[1.03] active:scale-95' : ''}`}
            style={{ animationDelay: `${delayMs}ms` }}
        >
            <div className="animate-breathe pointer-events-none absolute -top-3 -right-3 h-20 w-20">{icon}</div>
            <p className="text-3xl font-bold tabular-nums">{value}</p>
            <p className="mt-1 text-xs font-medium text-white/85">{label}</p>
        </button>
    );
}
