import type { ReactNode } from 'react';
import GlassCard from '@/Components/GlassCard';

interface PanelProps {
    title: string;
    icon?: ReactNode;
    right?: ReactNode;
    children: ReactNode;
}

export default function Panel({ title, icon, right, children }: PanelProps) {
    return (
        <GlassCard className="animate-fade-in-up p-4">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="flex items-center gap-1.5 text-sm font-semibold text-slate-200">
                    {icon}
                    {title}
                </h2>
                {right}
            </div>
            {children}
        </GlassCard>
    );
}
