import type { HTMLAttributes } from 'react';

// Shared glassmorphism surface: blurred translucent panel used by every card/panel in the app.
// Centralized here so the look stays consistent instead of retyping the same utility soup.
export default function GlassCard({ className = '', children, ...rest }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={`rounded-2xl border border-white/10 bg-white/5 shadow-xl shadow-black/30 backdrop-blur-xl ${className}`}
            {...rest}
        >
            {children}
        </div>
    );
}
