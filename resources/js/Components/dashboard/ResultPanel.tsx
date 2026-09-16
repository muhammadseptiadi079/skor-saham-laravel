import { useState } from 'react';
import type { AccuracyResponse, AnalysisResult, HistoryEntry } from '@/types';
import { SECTORS, DEFAULT_SECTOR } from '@/lib/sectors';
import GlassCard from '@/Components/GlassCard';
import ScoreGauge from '@/Components/charts/ScoreGauge';
import SubScoreBarChart from '@/Components/charts/SubScoreBarChart';
import HistoryLineChart from '@/Components/charts/HistoryLineChart';
import { StarIcon, NewsIcon, UsersIcon, GaugeIcon } from '@/Components/Icons';

function completenessColorClass(available: number, total: number): string {
    if (available === total) return 'border-emerald-300 bg-emerald-50 text-emerald-700';
    if (available >= total / 2) return 'border-amber-300 bg-amber-50 text-amber-700';

    return 'border-rose-300 bg-rose-50 text-rose-700';
}

function confidenceColorClass(level: string): string {
    if (level === 'Tinggi') return 'text-emerald-600';
    if (level === 'Sedang') return 'text-amber-600';

    return 'text-rose-600';
}

function money(v: number, currency: string): string {
    const isIdr = currency === 'IDR';
    const symbol = isIdr ? 'Rp' : '$';

    return `${symbol}${v.toLocaleString('id-ID', { maximumFractionDigits: isIdr ? 0 : 2 })}`;
}

// upsidePct arrives as a fraction (0.3 = 30%); avgForwardReturnPct from /api/accuracy arrives
// already multiplied by 100 — callers pass whichever unit matches.
function pctFraction(v: number): string {
    const p = v * 100;

    return `${p >= 0 ? '+' : ''}${p.toFixed(1)}%`;
}

function pctValue(v: number): string {
    return `${v >= 0 ? '+' : ''}${v}%`;
}

function movementColorClass(v: number): string {
    return v >= 0 ? 'text-emerald-600' : 'text-rose-600';
}

interface ResultPanelProps {
    result: AnalysisResult;
    savedAt: number | null;
    inWatchlist: boolean;
    onAddWatchlist: (sector: string) => void;
    trendEntries: HistoryEntry[];
    accuracy: AccuracyResponse | null;
}

function NoteList({ notes, delayBase = 0 }: { notes: string[]; delayBase?: number }) {
    if (notes.length === 0) return <p className="text-sm text-slate-500">Tidak ada catatan.</p>;
    return (
        <ul className="space-y-1.5">
            {notes.map((note, i) => (
                <li
                    key={note}
                    className="animate-row-in text-sm text-slate-700"
                    style={{ animationDelay: `${delayBase + i * 60}ms` }}
                >
                    {note}
                </li>
            ))}
        </ul>
    );
}

// Below this many graded samples, an average forward return is too likely to be noise to show
// next to a specific stock's result — same conservative threshold philosophy as the other
// backtest-derived reports (see SubScoreAccuracyService on the backend).
const MIN_SAMPLE_FOR_HISTORICAL_NOTE = 10;

export default function ResultPanel({ result, savedAt, inWatchlist, onAddWatchlist, trendEntries, accuracy }: ResultPanelProps) {
    const [sector, setSector] = useState<string>(DEFAULT_SECTOR);
    const when = new Date(savedAt || result.generatedAt);
    const isBearish = (result.trading.score ?? 0) <= -0.5 || (result.longterm.score ?? 0) <= -0.5;
    const historicalReturn =
        accuracy?.byLabel.find((row) => row.label === result.trading.label && row.sampleSize >= MIN_SAMPLE_FOR_HISTORICAL_NOTE) ?? null;

    return (
        <GlassCard className="animate-fade-in-up p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-display text-xl font-bold tracking-tight text-slate-900">
                        {result.name} <span className="text-slate-600">({result.ticker})</span>
                    </h2>
                    <p className="text-xs text-slate-500">Diperbarui: {when.toLocaleString('id-ID')}</p>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                    {isBearish && (
                        <span className="animate-pulse-ring rounded-full border border-rose-300 bg-rose-50 px-2 py-1 text-[10px] font-semibold tracking-wide text-rose-700 uppercase">
                            Perlu perhatian
                        </span>
                    )}
                    <span
                        className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-semibold whitespace-nowrap ${completenessColorClass(result.dataCompleteness.available, result.dataCompleteness.total)}`}
                        title="Jumlah sub-skor yang datanya tersedia untuk analisis ini"
                    >
                        <GaugeIcon className="h-3 w-3" />
                        {result.dataCompleteness.available}/{result.dataCompleteness.total} sub-skor tersedia
                    </span>
                    {result.lowLiquidity && (
                        <span
                            className="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2 py-1 text-[10px] font-semibold whitespace-nowrap text-amber-700"
                            title="Volume transaksi harian tipis — sinyal teknikal kurang bisa diandalkan"
                        >
                            Likuiditas rendah
                        </span>
                    )}
                </div>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-3">
                <GlassCard className="p-3">
                    <ScoreGauge score={result.longterm.score} label={result.longterm.label} title="Jangka Panjang" />
                    {result.longterm.confidence && (
                        <p
                            className={`mt-1 text-center text-[11px] font-medium ${confidenceColorClass(result.longterm.confidence)}`}
                            title={result.longterm.confidenceNote ?? undefined}
                        >
                            Keyakinan: {result.longterm.confidence}
                        </p>
                    )}
                </GlassCard>
                <GlassCard className="p-3">
                    <ScoreGauge score={result.trading.score} label={result.trading.label} title="Trading" />
                    {result.trading.confidence && (
                        <p
                            className={`mt-1 text-center text-[11px] font-medium ${confidenceColorClass(result.trading.confidence)}`}
                            title={result.trading.confidenceNote ?? undefined}
                        >
                            Keyakinan: {result.trading.confidence}
                        </p>
                    )}
                </GlassCard>
            </div>

            {result.horizonAlignment.aligned === false && result.horizonAlignment.note && (
                <div className="mt-3 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    {result.horizonAlignment.note}
                </div>
            )}

            {(result.priceTarget || historicalReturn) && (
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    {result.priceTarget && (
                        <GlassCard className="p-3.5">
                            <p className="text-[11px] font-semibold tracking-wide text-slate-600 uppercase">Target Analis</p>
                            <p className={`mt-1 text-2xl font-bold ${movementColorClass(result.priceTarget.upsidePct)}`}>
                                {pctFraction(result.priceTarget.upsidePct)}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Konsensus target harga analis {money(result.priceTarget.targetPrice, result.currency)} dari harga
                                sekarang — data eksternal, bukan prediksi aplikasi ini.
                            </p>
                        </GlassCard>
                    )}
                    {historicalReturn && (
                        <GlassCard className="p-3.5">
                            <p className="text-[11px] font-semibold tracking-wide text-slate-600 uppercase">
                                Riwayat Label &quot;{historicalReturn.label}&quot;
                            </p>
                            <p className={`mt-1 text-2xl font-bold ${movementColorClass(historicalReturn.avgForwardReturnPct)}`}>
                                {pctValue(historicalReturn.avgForwardReturnPct)}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Rata-rata pergerakan historis semua saham berlabel ini dalam ~20 hari perdagangan (dari{' '}
                                {historicalReturn.sampleSize} sampel, akurasi arah {historicalReturn.accuracy}%) — bukan prediksi
                                untuk saham ini secara spesifik.
                            </p>
                        </GlassCard>
                    )}
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                {!inWatchlist && (
                    <select
                        value={sector}
                        onChange={(e) => setSector(e.target.value)}
                        className="rounded-xl border border-slate-300 bg-slate-50 px-2.5 py-2 text-sm text-slate-700"
                        title="Sektor untuk pengelompokan watchlist"
                    >
                        {SECTORS.map((s) => (
                            <option key={s} value={s} className="bg-white text-slate-800">
                                {s}
                            </option>
                        ))}
                    </select>
                )}
                <button
                    type="button"
                    onClick={() => onAddWatchlist(sector)}
                    disabled={inWatchlist}
                    className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-medium transition-transform hover:scale-105 active:scale-95 disabled:hover:scale-100 ${
                        inWatchlist
                            ? 'border-amber-300 bg-amber-50 text-amber-700'
                            : 'border-slate-300 bg-slate-50 text-slate-800 hover:border-slate-400'
                    }`}
                >
                    <StarIcon filled={inWatchlist} className="h-4 w-4" />
                    {inWatchlist ? 'Ada di watchlist' : 'Tambah ke watchlist'}
                </button>
            </div>

            <div className="mt-5">
                <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">Rincian Sub-skor</h3>
                <GlassCard className="p-3">
                    <SubScoreBarChart
                        rows={[
                            { label: 'Fundamental', score: result.subScores.fundamentals.score },
                            { label: 'Berita', score: result.subScores.news.score },
                            { label: 'Momentum', score: result.subScores.momentum.score },
                            { label: 'Tren Panjang', score: result.subScores.momentumLongTerm.score },
                            { label: 'Kepemilikan', score: result.subScores.ownership.score },
                        ]}
                    />
                </GlassCard>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <GlassCard className="p-3.5">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">Fundamental</h4>
                    <NoteList notes={result.subScores.fundamentals.notes} />
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        <NewsIcon className="h-3.5 w-3.5" /> Berita
                    </h4>
                    <NoteList notes={result.subScores.news.notes} />
                    {result.subScores.news.topArticles.length > 0 && (
                        <ul className="mt-2 space-y-1 border-t border-slate-200 pt-2">
                            {result.subScores.news.topArticles.map((a) =>
                                a.url ? (
                                    <li key={a.url} className="text-xs">
                                        <a
                                            href={a.url}
                                            target="_blank"
                                            rel="noopener"
                                            className="text-sky-700 hover:text-sky-700 hover:underline"
                                        >
                                            {a.title}
                                        </a>
                                    </li>
                                ) : (
                                    <li key={a.title} className="text-xs text-slate-700">
                                        {a.title}
                                        {a.source && <span className="text-slate-500"> — {a.source}</span>}
                                    </li>
                                )
                            )}
                        </ul>
                    )}
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">Momentum & Volume</h4>
                    <NoteList notes={result.subScores.momentum.notes} />
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        Tren Jangka Panjang
                    </h4>
                    <NoteList notes={result.subScores.momentumLongTerm.notes} />
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        <UsersIcon className="h-3.5 w-3.5" /> Kepemilikan & Insider
                    </h4>
                    <NoteList notes={result.subScores.ownership.notes} />
                    {result.subScores.ownership.transactions.length > 0 && (
                        <ul className="mt-2 space-y-1 border-t border-slate-200 pt-2">
                            {result.subScores.ownership.transactions.map((t, i) => (
                                <li key={`${t.insiderName}-${i}`} className="text-xs text-slate-700">
                                    {t.type === 'buy' ? '↑ Beli' : '↓ Jual'} — {t.insiderName} ({t.role}),{' '}
                                    {Number(t.shares).toLocaleString('id-ID')} lembar
                                    {t.date ? ` · ${t.date}` : ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </GlassCard>
            </div>

            {trendEntries.length >= 2 && (
                <div className="mt-5">
                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                        Tren Skor Trading
                    </h3>
                    <GlassCard className="p-3">
                        <HistoryLineChart entries={trendEntries} />
                    </GlassCard>
                </div>
            )}

            <p className="mt-5 border-t border-slate-200 pt-3 text-xs text-slate-500">{result.disclaimer}</p>
        </GlassCard>
    );
}
