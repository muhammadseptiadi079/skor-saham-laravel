import type { AnalysisResult, HistoryEntry } from '@/types';
import GlassCard from '@/Components/GlassCard';
import ScoreGauge from '@/Components/charts/ScoreGauge';
import SubScoreBarChart from '@/Components/charts/SubScoreBarChart';
import HistoryLineChart from '@/Components/charts/HistoryLineChart';
import { StarIcon, NewsIcon, UsersIcon, GaugeIcon } from '@/Components/Icons';

function completenessColorClass(available: number, total: number): string {
    if (available === total) return 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300';
    if (available >= total / 2) return 'border-amber-400/30 bg-amber-400/10 text-amber-300';

    return 'border-rose-400/30 bg-rose-400/10 text-rose-300';
}

interface ResultPanelProps {
    result: AnalysisResult;
    savedAt: number | null;
    inWatchlist: boolean;
    onAddWatchlist: () => void;
    trendEntries: HistoryEntry[];
}

function NoteList({ notes, delayBase = 0 }: { notes: string[]; delayBase?: number }) {
    if (notes.length === 0) return <p className="text-sm text-slate-500">Tidak ada catatan.</p>;
    return (
        <ul className="space-y-1.5">
            {notes.map((note, i) => (
                <li
                    key={note}
                    className="animate-row-in text-sm text-slate-300"
                    style={{ animationDelay: `${delayBase + i * 60}ms` }}
                >
                    {note}
                </li>
            ))}
        </ul>
    );
}

export default function ResultPanel({ result, savedAt, inWatchlist, onAddWatchlist, trendEntries }: ResultPanelProps) {
    const when = new Date(savedAt || result.generatedAt);
    const isBearish = (result.trading.score ?? 0) <= -0.5 || (result.longterm.score ?? 0) <= -0.5;

    return (
        <GlassCard className="animate-fade-in-up p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-bold text-white">
                        {result.name} <span className="text-slate-400">({result.ticker})</span>
                    </h2>
                    <p className="text-xs text-slate-500">Diperbarui: {when.toLocaleString('id-ID')}</p>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                    {isBearish && (
                        <span className="animate-pulse-ring rounded-full border border-rose-400/30 bg-rose-500/10 px-2 py-1 text-[10px] font-semibold tracking-wide text-rose-300 uppercase">
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
                </div>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-3">
                <GlassCard className="p-3">
                    <ScoreGauge score={result.longterm.score} label={result.longterm.label} title="Jangka Panjang" />
                </GlassCard>
                <GlassCard className="p-3">
                    <ScoreGauge score={result.trading.score} label={result.trading.label} title="Trading" />
                </GlassCard>
            </div>

            <button
                type="button"
                onClick={onAddWatchlist}
                disabled={inWatchlist}
                className={`mt-4 inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-medium transition-transform hover:scale-105 active:scale-95 disabled:hover:scale-100 ${
                    inWatchlist
                        ? 'border-amber-400/40 bg-amber-400/10 text-amber-300'
                        : 'border-white/15 bg-white/5 text-slate-200 hover:border-white/30'
                }`}
            >
                <StarIcon filled={inWatchlist} className="h-4 w-4" />
                {inWatchlist ? 'Ada di watchlist' : 'Tambah ke watchlist'}
            </button>

            <div className="mt-5">
                <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Rincian Sub-skor</h3>
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
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Fundamental</h4>
                    <NoteList notes={result.subScores.fundamentals.notes} />
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                        <NewsIcon className="h-3.5 w-3.5" /> Berita
                    </h4>
                    <NoteList notes={result.subScores.news.notes} />
                    {result.subScores.news.topArticles.length > 0 && (
                        <ul className="mt-2 space-y-1 border-t border-white/10 pt-2">
                            {result.subScores.news.topArticles.map((a) => (
                                <li key={a.url ?? a.title} className="text-xs">
                                    <a
                                        href={a.url ?? '#'}
                                        target="_blank"
                                        rel="noopener"
                                        className="text-sky-300 hover:text-sky-200 hover:underline"
                                    >
                                        {a.title}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Momentum & Volume</h4>
                    <NoteList notes={result.subScores.momentum.notes} />
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                        Tren Jangka Panjang
                    </h4>
                    <NoteList notes={result.subScores.momentumLongTerm.notes} />
                </GlassCard>

                <GlassCard className="p-3.5">
                    <h4 className="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                        <UsersIcon className="h-3.5 w-3.5" /> Kepemilikan & Insider
                    </h4>
                    <NoteList notes={result.subScores.ownership.notes} />
                    {result.subScores.ownership.transactions.length > 0 && (
                        <ul className="mt-2 space-y-1 border-t border-white/10 pt-2">
                            {result.subScores.ownership.transactions.map((t, i) => (
                                <li key={`${t.insiderName}-${i}`} className="text-xs text-slate-300">
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
                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                        Tren Skor Trading
                    </h3>
                    <GlassCard className="p-3">
                        <HistoryLineChart entries={trendEntries} />
                    </GlassCard>
                </div>
            )}

            <p className="mt-5 border-t border-white/10 pt-3 text-xs text-slate-500">{result.disclaimer}</p>
        </GlassCard>
    );
}
