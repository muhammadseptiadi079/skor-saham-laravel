import type { AccuracyResponse, ConfidenceCalibration } from '@/types';
import Panel from './Panel';
import { GaugeIcon } from '@/Components/Icons';

const SUB_SCORE_LABELS: Record<string, string> = {
    fundamentals: 'Fundamental',
    news: 'Berita',
    momentum: 'Momentum (Trading)',
    momentumLongTerm: 'Tren Panjang',
    ownership: 'Kepemilikan',
};

function accuracyColorClass(accuracy: number): string {
    if (accuracy >= 60) return 'text-emerald-600';
    if (accuracy >= 45) return 'text-amber-600';

    return 'text-rose-600';
}

// Always rendered, in one of three states — a report that goes quiet while data is still
// accumulating reads as broken, not as "nothing to say yet" (see confidenceCalibrationReport()
// on the backend for the same reasoning).
function calibrationBoxClass(status: ConfidenceCalibration['status']): string {
    if (status === 'ok') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (status === 'needs_review') return 'border-amber-200 bg-amber-50 text-amber-700';

    return 'border-slate-200 bg-slate-50 text-slate-600';
}

function CalibrationNote({ calibration }: { calibration: ConfidenceCalibration }) {
    return (
        <p className={`mt-2 rounded-xl border px-3 py-2 text-xs ${calibrationBoxClass(calibration.status)}`}>
            {calibration.message}
        </p>
    );
}

export default function AccuracyPanel({ data }: { data: AccuracyResponse | null }) {
    if (!data) {
        return (
            <Panel title="Akurasi Historis" icon={<GaugeIcon className="h-4 w-4 text-sky-700" />}>
                <p className="text-sm text-slate-500">Memuat data akurasi...</p>
            </Panel>
        );
    }

    if (data.sampleSize === 0) {
        return (
            <Panel title="Akurasi Historis" icon={<GaugeIcon className="h-4 w-4 text-sky-700" />}>
                <p className="text-sm text-slate-500">
                    Belum ada data yang cukup umur untuk dievaluasi. Skor "trading" baru dinilai
                    setelah ~1 bulan (lewat <code className="rounded bg-slate-100 px-1 py-0.5 text-xs">php artisan stocks:evaluate-backtest</code>),
                    supaya ada cukup waktu untuk tahu apakah arah harganya benar.
                </p>
                <CalibrationNote calibration={data.confidenceCalibration} />
            </Panel>
        );
    }

    const subScoreRows = data.subScoreAccuracy.filter((row) => row.sampleSize > 0);

    return (
        <Panel title="Akurasi Historis" icon={<GaugeIcon className="h-4 w-4 text-sky-700" />}>
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
                        className="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        <span className="text-sm text-slate-800">{row.label}</span>
                        <span className="flex items-center gap-3 text-xs text-slate-600">
                            <span>{row.sampleSize} sampel</span>
                            <span className={`font-semibold ${accuracyColorClass(row.accuracy)}`}>{row.accuracy}%</span>
                        </span>
                    </li>
                ))}
            </ul>

            {data.byMarketRegime.length > 0 && (
                <div className="mt-4 border-t border-slate-200 pt-3">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        Akurasi per Kondisi Pasar
                    </h4>
                    <p className="mb-2 text-xs text-slate-500">
                        Supaya ketahuan kalau sinyal "Buy" itu memang bagus, atau cuma kelihatan
                        bagus karena kebetulan pasar lagi naik secara keseluruhan.
                    </p>
                    <ul className="flex flex-col gap-1.5">
                        {data.byMarketRegime.map((row) => (
                            <li key={row.label} className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-slate-700">{row.label}</span>
                                <span className="flex items-center gap-2 text-slate-500">
                                    <span>{row.sampleSize} sampel</span>
                                    <span className={`font-semibold ${accuracyColorClass(row.accuracy)}`}>{row.accuracy}%</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mt-4 border-t border-slate-200 pt-3">
                <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                    Akurasi per Level Keyakinan
                </h4>
                <p className="mb-2 text-xs text-slate-500">
                    Memvalidasi skor "Keyakinan" itu sendiri — kalau akurasi "Tinggi" ternyata tidak
                    jauh beda dari "Rendah", berarti skor keyakinannya belum benar-benar berarti apa-apa.
                </p>
                {data.byConfidence.length > 0 && (
                    <ul className="flex flex-col gap-1.5">
                        {data.byConfidence.map((row) => (
                            <li key={row.label} className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-slate-700">{row.label}</span>
                                <span className="flex items-center gap-2 text-slate-500">
                                    <span>{row.sampleSize} sampel</span>
                                    <span className={`font-semibold ${accuracyColorClass(row.accuracy)}`}>{row.accuracy}%</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
                <CalibrationNote calibration={data.confidenceCalibration} />
            </div>

            {data.byWatchlistSector.length > 0 && (
                <div className="mt-4 border-t border-slate-200 pt-3">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        Akurasi per Sektor Watchlist
                    </h4>
                    <p className="mb-2 text-xs text-slate-500">
                        Sektor mana sinyalnya lebih sering benar — cuma menghitung saham yang ada
                        di watchlist dan sudah diberi sektor.
                    </p>
                    <ul className="flex flex-col gap-1.5">
                        {data.byWatchlistSector.map((row) => (
                            <li key={row.label} className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-slate-700">{row.label}</span>
                                <span className="flex items-center gap-2 text-slate-500">
                                    <span>{row.sampleSize} sampel</span>
                                    <span className={`font-semibold ${accuracyColorClass(row.accuracy)}`}>{row.accuracy}%</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {subScoreRows.length > 0 && (
                <div className="mt-4 border-t border-slate-200 pt-3">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        Akurasi Arah per Sub-skor
                    </h4>
                    <p className="mb-2 text-xs text-slate-500">
                        Seberapa sering arah sub-skor ini (positif/negatif) cocok dengan arah harga
                        yang benar-benar terjadi — bahan pertimbangan kalau suatu saat mau
                        menyesuaikan bobot di <code className="rounded bg-slate-100 px-1 py-0.5">ScoringEngine::WEIGHTS</code>.
                    </p>
                    <ul className="flex flex-col gap-1.5">
                        {subScoreRows.map((row) => (
                            <li key={row.subScore} className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-slate-700">{SUB_SCORE_LABELS[row.subScore] ?? row.subScore}</span>
                                <span className="flex items-center gap-2 text-slate-500">
                                    <span>{row.sampleSize} sampel</span>
                                    <span className={`font-semibold ${accuracyColorClass(row.directionalAccuracy ?? 0)}`}>
                                        {row.directionalAccuracy}%
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {data.weightSuggestions.length > 0 && (
                <div className="mt-4 border-t border-slate-200 pt-3">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        Saran Evaluasi Bobot
                    </h4>
                    <p className="mb-2 text-xs text-slate-500">
                        Cuma saran untuk dibaca manusia — <strong>tidak pernah otomatis mengubah</strong>{' '}
                        <code className="rounded bg-slate-100 px-1 py-0.5">ScoringEngine::WEIGHTS</code>.
                        Sampel sekecil ini terlalu berisiko untuk auto-tuning.
                    </p>
                    <ul className="flex flex-col gap-1.5">
                        {data.weightSuggestions.map((row) => (
                            <li key={row.subScore} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                                <span className="font-semibold text-slate-800">{SUB_SCORE_LABELS[row.subScore] ?? row.subScore}</span>
                                <span className="text-slate-600"> — {row.suggestion}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Panel>
    );
}
