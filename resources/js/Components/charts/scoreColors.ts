// Shared color mapping so every chart reads scores the same way as ScoringEngine's labels
// (see app/Services/ScoringEngine.php labelFor/describe): >=0.5 strong positive ... <=-0.5 strong negative.
export function colorForScore(score: number | null | undefined): string {
    if (score === null || score === undefined) return '#64748b'; // slate-500, "data tidak cukup"
    if (score >= 0.5) return '#22c55e'; // green-500
    if (score >= 0.15) return '#4ade80'; // green-400
    if (score > -0.15) return '#eab308'; // yellow-500
    if (score > -0.5) return '#f87171'; // red-400
    return '#ef4444'; // red-500
}

export function textClassForScore(score: number | null | undefined): string {
    if (score === null || score === undefined) return 'text-slate-400';
    if (score >= 0.15) return 'text-emerald-400';
    if (score > -0.15) return 'text-amber-400';
    return 'text-rose-400';
}
