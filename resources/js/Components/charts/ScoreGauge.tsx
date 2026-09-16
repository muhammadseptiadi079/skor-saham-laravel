import { colorForScore } from './scoreColors';

interface ScoreGaugeProps {
    score: number | null;
    label: string;
    title: string;
}

const CX = 100;
const CY = 100;
const R = 82;

function polarToCartesian(cx: number, cy: number, r: number, angleDeg: number) {
    const rad = (angleDeg * Math.PI) / 180;
    return { x: cx + r * Math.cos(rad), y: cy - r * Math.sin(rad) };
}

// Describes an SVG arc along the upper semicircle between two scores in [-1, 1].
function arcForScoreRange(from: number, to: number): string {
    const angleFor = (s: number) => 180 - ((s + 1) / 2) * 180;
    const start = polarToCartesian(CX, CY, R, angleFor(from));
    const end = polarToCartesian(CX, CY, R, angleFor(to));
    return `M ${start.x} ${start.y} A ${R} ${R} 0 0 1 ${end.x} ${end.y}`;
}

// Bands mirror ScoringEngine::labelFor's thresholds, so the gauge always agrees with the label.
// Same palette as colorForScore, kept in sync manually since these are SVG stroke colors, not
// Tailwind classes.
const BANDS: Array<[number, number, string]> = [
    [-1, -0.5, '#b91c1c'],
    [-0.5, -0.15, '#dc2626'],
    [-0.15, 0.15, '#d97706'],
    [0.15, 0.5, '#22c55e'],
    [0.5, 1, '#16a34a'],
];

export default function ScoreGauge({ score, label, title }: ScoreGaugeProps) {
    const needleAngle = score === null ? 90 : 180 - ((score + 1) / 2) * 180;
    const needleTip = polarToCartesian(CX, CY, R - 14, needleAngle);

    return (
        <div className="flex flex-col items-center">
            <svg viewBox="0 0 200 118" className="w-full max-w-[220px]">
                {BANDS.map(([from, to, color]) => (
                    <path
                        key={from}
                        d={arcForScoreRange(from, to)}
                        stroke={score === null ? '#cbd5e1' : color}
                        strokeWidth={14}
                        strokeLinecap="butt"
                        fill="none"
                    />
                ))}
                {score !== null && (
                    <g className="animate-pop-in" style={{ transformOrigin: `${CX}px ${CY}px` }}>
                        <line
                            x1={CX}
                            y1={CY}
                            x2={needleTip.x}
                            y2={needleTip.y}
                            stroke="#1e293b"
                            strokeWidth={3}
                            strokeLinecap="round"
                        />
                        <circle cx={CX} cy={CY} r={6} fill="#1e293b" />
                    </g>
                )}
            </svg>
            <div className="-mt-4 text-center">
                <p className="text-xs font-medium tracking-wide text-slate-600 uppercase">{title}</p>
                <p className="font-display text-2xl font-bold" style={{ color: colorForScore(score) }}>
                    {label}
                </p>
                {score !== null && <p className="text-xs text-slate-500">skor {score.toFixed(2)}</p>}
            </div>
        </div>
    );
}
