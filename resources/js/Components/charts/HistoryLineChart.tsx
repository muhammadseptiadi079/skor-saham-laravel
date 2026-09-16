import type { HistoryEntry } from '@/types';
import { colorForScore } from './scoreColors';

const W = 320;
const H = 120;
const PAD_X = 12;
const PAD_Y = 16;

function scoreToY(score: number): number {
    const t = (score + 1) / 2; // 0..1, 0 = bottom
    return PAD_Y + (1 - t) * (H - PAD_Y * 2);
}

export default function HistoryLineChart({ entries }: { entries: HistoryEntry[] }) {
    // entries arrive newest-first from the API; the chart reads left-to-right as time passing.
    const points = [...entries].reverse().filter((e) => e.trading_score !== null);

    if (points.length < 2) {
        return <p className="text-sm text-slate-500">Butuh minimal 2 riwayat analisis untuk menampilkan tren.</p>;
    }

    const step = (W - PAD_X * 2) / (points.length - 1);
    const coords = points.map((p, i) => ({
        x: PAD_X + i * step,
        y: scoreToY(p.trading_score as number),
        entry: p,
    }));
    const linePath = coords.map((c, i) => `${i === 0 ? 'M' : 'L'} ${c.x} ${c.y}`).join(' ');
    const areaPath = `${linePath} L ${coords[coords.length - 1].x} ${H - PAD_Y} L ${coords[0].x} ${H - PAD_Y} Z`;
    const zeroY = scoreToY(0);

    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="animate-fade-in-up h-28 w-full">
            <defs>
                <linearGradient id="history-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#38bdf8" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="#38bdf8" stopOpacity={0} />
                </linearGradient>
            </defs>
            <line x1={PAD_X} y1={zeroY} x2={W - PAD_X} y2={zeroY} stroke="#cbd5e1" strokeDasharray="4 4" />
            <path d={areaPath} fill="url(#history-fill)" stroke="none" />
            <path d={linePath} fill="none" stroke="#38bdf8" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
            {coords.map((c) => (
                <circle key={c.entry.id} cx={c.x} cy={c.y} r={3} fill={colorForScore(c.entry.trading_score)}>
                    <title>
                        {c.entry.trading_label} ({c.entry.trading_score?.toFixed(2)}) —{' '}
                        {new Date(c.entry.generated_at).toLocaleDateString('id-ID')}
                    </title>
                </circle>
            ))}
        </svg>
    );
}
