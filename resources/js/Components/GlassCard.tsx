import type { HTMLAttributes } from 'react';

// Shared surface used by every card/panel in the app. Centralized here so the look stays
// consistent instead of retyping the same utility soup.
export default function GlassCard({ className = '', children, ...rest }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={`rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 ${className}`} {...rest}>
            {children}
        </div>
    );
}
