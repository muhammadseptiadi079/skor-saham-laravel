import { colorForScore } from './scoreColors';

interface ScoreBarProps {
    score: number | null;
    label: string;
    title: string;
}

// Same -1..1 bands/colors as the old needle gauge (ScoreGauge) — kept in sync manually since
// these are raw hex values, not Tailwind classes. Replaces the speedometer look with a
// horizontal track + marker, which reads faster and matches SubScoreBarChart's bar language
// instead of introducing a second, dated-looking chart style.
const BANDS: Array<[number, number, string]> = [
    [-1, -0.5, '#b91c1c'],
    [-0.5, -0.15, '#dc2626'],
    [-0.15, 0.15, '#d97706'],
    [0.15, 0.5, '#22c55e'],
    [0.5, 1, '#16a34a'],
];

export default function ScoreBar({ score, label, title }: ScoreBarProps) {
    const percent = score === null ? 50 : ((Math.max(-1, Math.min(1, score)) + 1) / 2) * 100;

    return (
        <div className="flex flex-col items-center gap-1.5 text-center">
            <p className="text-xs font-medium tracking-wide text-slate-600 uppercase">{title}</p>
            <p className="font-display text-2xl font-bold" style={{ color: colorForScore(score) }}>
                {label}
            </p>
            <div className="relative mt-1 h-2.5 w-full max-w-[220px] overflow-hidden rounded-full">
                <div aria-hidden className="absolute inset-0 flex">
                    {BANDS.map(([from, to, color]) => (
                        <div
                            key={from}
                            style={{ width: `${((to - from) / 2) * 100}%`, backgroundColor: score === null ? '#cbd5e1' : color }}
                        />
                    ))}
                </div>
                {score !== null && (
                    <div
                        className="animate-pop-in absolute top-1/2 h-4 w-2 -translate-y-1/2 rounded-full bg-black ring-2 ring-white"
                        style={{ left: `calc(${percent}% - 4px)` }}
                    />
                )}
            </div>
            {score !== null && <p className="text-xs text-slate-500">skor {score.toFixed(2)}</p>}
        </div>
    );
}
