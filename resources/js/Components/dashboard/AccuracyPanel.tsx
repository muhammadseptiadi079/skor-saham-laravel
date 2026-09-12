import type { AccuracyResponse } from '@/types';
import Panel from './Panel';
import { GaugeIcon } from '@/Components/Icons';

function accuracyColorClass(accuracy: number): string {
    if (accuracy >= 60) return 'text-emerald-400';
    if (accuracy >= 45) return 'text-amber-400';

    return 'text-rose-400';
}

export default function AccuracyPanel({ data }: { data: AccuracyResponse | null }) {
    if (!data || data.sampleSize === 0) {
        return (
            <Panel title="Akurasi Historis" icon={<GaugeIcon className="h-4 w-4 text-sky-300" />}>
                <p className="text-sm text-slate-500">
                    Belum ada data yang cukup umur untuk dievaluasi. Skor "trading" baru dinilai
                    setelah ~1 bulan (lewat <code className="rounded bg-white/10 px-1 py-0.5 text-xs">php artisan stocks:evaluate-backtest</code>),
                    supaya ada cukup waktu untuk tahu apakah arah harganya benar.
                </p>
            </Panel>
        );
    }

    return (
        <Panel title="Akurasi Historis" icon={<GaugeIcon className="h-4 w-4 text-sky-300" />}>
            <p className="mb-3 text-xs text-slate-500">
                Persentase label "trading" yang arah prediksinya benar ~20 hari perdagangan
                kemudian — dihitung dari riwayat analisis, bukan klaim di muka.
            </p>
            <div className="mb-4 flex items-baseline gap-2">
                <span className={`text-3xl font-bold ${accuracyColorClass(data.accuracy ?? 0)}`}>
                    {data.accuracy}%
                </span>
                <span className="text-xs text-slate-500">dari {data.sampleSize} analisis yang sudah dievaluasi</span>
            </div>
            <ul className="flex flex-col gap-2">
                {data.byLabel.map((row) => (
                    <li
                        key={row.label}
                        className="flex items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2"
                    >
                        <span className="text-sm text-slate-200">{row.label}</span>
                        <span className="flex items-center gap-3 text-xs text-slate-400">
                            <span>{row.sampleSize} sampel</span>
                            <span className={`font-semibold ${accuracyColorClass(row.accuracy)}`}>{row.accuracy}%</span>
                        </span>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}
