// Shared color mapping so every chart reads scores the same way as ScoringEngine's labels
// (see app/Services/ScoringEngine.php labelFor/describe): >=0.5 strong positive ... <=-0.5 strong negative.
export function colorForScore(score: number | null | undefined): string {
    if (score === null || score === undefined) return '#64748b'; // slate-500, "data tidak cukup"
    if (score >= 0.5) return '#16a34a'; // green-600
    if (score >= 0.15) return '#22c55e'; // green-500
    if (score > -0.15) return '#d97706'; // amber-600
    if (score > -0.5) return '#dc2626'; // red-600
    return '#b91c1c'; // red-700
}

export function textClassForScore(score: number | null | undefined): string {
    if (score === null || score === undefined) return 'text-slate-600';
    if (score >= 0.15) return 'text-emerald-600';
    if (score > -0.15) return 'text-amber-600';
    return 'text-rose-600';
}
