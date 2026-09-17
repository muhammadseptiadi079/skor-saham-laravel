import type { ReactNode } from 'react';

interface EmptyStateProps {
    icon: ReactNode;
    message: ReactNode;
}

// Small icon + message for a panel's first-run "nothing here yet" state — used only for the
// handful of top-level empty states a brand-new user actually sees (watchlist, riwayat,
// screener, dst.), not every minor "no notes for this sub-score" spot, so it stays a signal
// instead of decoration repeated everywhere.
export default function EmptyState({ icon, message }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center gap-2 py-5 text-center">
            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                {icon}
            </div>
            <p className="max-w-xs text-sm text-slate-500">{message}</p>
        </div>
    );
}
