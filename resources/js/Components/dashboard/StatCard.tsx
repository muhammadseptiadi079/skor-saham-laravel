import type { ReactNode } from 'react';

interface StatCardProps {
    label: string;
    value: number;
    icon: ReactNode;
    gradient: string;
    delayMs?: number;
}

export default function StatCard({ label, value, icon, gradient, delayMs = 0 }: StatCardProps) {
    return (
        <div
            className={`animate-pop-in relative overflow-hidden rounded-2xl bg-gradient-to-br ${gradient} p-4 text-white shadow-lg`}
            style={{ animationDelay: `${delayMs}ms` }}
        >
            <div className="animate-breathe pointer-events-none absolute -top-3 -right-3 h-20 w-20">{icon}</div>
            <p className="text-3xl font-bold tabular-nums">{value}</p>
            <p className="mt-1 text-xs font-medium text-white/85">{label}</p>
        </div>
    );
}
