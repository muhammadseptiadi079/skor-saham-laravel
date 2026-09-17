import { colorForScore } from './scoreColors';

interface Row {
    label: string;
    score: number | null;
    /** Historical directional-accuracy note for this sub-score (from /api/accuracy), if enough
     * backtest samples exist to say anything — surfaces the sub-score's own track record right
     * where the decision is made, instead of only in the separate Akurasi tab. */
    accuracyNote?: string;
}

const BAR_VIEWBOX_WIDTH = 200;
const CENTER = BAR_VIEWBOX_WIDTH / 2;
const HALF_SPAN = 92;

export default function SubScoreBarChart({ rows }: { rows: Row[] }) {
    return (
        <div className="flex flex-col gap-3">
            {rows.map((row, i) => {
                const magnitude = row.score === null ? 0 : Math.min(Math.abs(row.score), 1) * HALF_SPAN;
                const barX = row.score !== null && row.score < 0 ? CENTER - magnitude : CENTER;
                const color = colorForScore(row.score);

                return (
                    <div
                        key={row.label}
                        className="animate-row-in"
                        style={{ animationDelay: `${i * 60}ms` }}
                    >
                        <div className="grid grid-cols-[5.5rem_1fr_3rem] items-center gap-2 sm:grid-cols-[6.5rem_1fr_3rem]">
                            <span className="truncate text-xs font-medium text-slate-700">{row.label}</span>
                            <svg viewBox={`0 0 ${BAR_VIEWBOX_WIDTH} 16`} className="h-3.5 w-full" preserveAspectRatio="none">
                                <rect x={0} y={6} width={BAR_VIEWBOX_WIDTH} height={4} rx={2} fill="#e2e8f0" />
                                <line x1={CENTER} y1={0} x2={CENTER} y2={16} stroke="#94a3b8" strokeWidth={1} />
                                {row.score !== null && (
                                    <rect x={barX} y={4} width={magnitude} height={8} rx={4} fill={color} />
                                )}
                            </svg>
                            <span className="text-right text-xs font-semibold" style={{ color }}>
                                {row.score === null ? '—' : row.score.toFixed(2)}
                            </span>
                        </div>
                        {row.accuracyNote && (
                            <p className="mt-0.5 pl-[calc(5.5rem+0.5rem)] text-[10px] text-slate-400 sm:pl-[calc(6.5rem+0.5rem)]">
                                {row.accuracyNote}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
