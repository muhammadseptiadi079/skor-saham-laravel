import { useEffect, useState } from 'react';
import type { ManualNewsItem, Market, TickerCandidate } from '@/types';
import Panel from './Panel';
import { NewsIcon, CloseIcon } from '@/Components/Icons';
import * as api from '@/lib/api';
import { ApiRequestError } from '@/lib/api';

interface ManualNewsPanelProps {
    defaultTicker?: string;
    defaultMarket?: Market;
}

function sentimentLabel(score: number): { text: string; className: string } {
    if (score >= 0.5) return { text: 'sangat positif', className: 'text-emerald-600' };
    if (score >= 0.15) return { text: 'positif', className: 'text-emerald-600' };
    if (score > -0.15) return { text: 'netral', className: 'text-slate-600' };
    if (score > -0.5) return { text: 'negatif', className: 'text-rose-600' };
    return { text: 'sangat negatif', className: 'text-rose-600' };
}

function candidateLabel(c: TickerCandidate): string {
    return c.name ? `${c.ticker} — ${c.name}` : c.ticker;
}

export default function ManualNewsPanel({ defaultTicker, defaultMarket }: ManualNewsPanelProps) {
    // Only used to *view* news already tagged to the stock currently on screen — the upload flow
    // below no longer needs a ticker up front, this app finds it from the news text itself.
    const [viewTicker, setViewTicker] = useState(defaultTicker ?? '');
    const [viewMarket, setViewMarket] = useState<Market>(defaultMarket ?? 'idx');
    const [items, setItems] = useState<ManualNewsItem[]>([]);

    const [text, setText] = useState('');
    const [image, setImage] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Set once detection runs: null candidates = detection hasn't run yet for the current draft;
    // an array (possibly empty) means it ran and is waiting on the user (ambiguous) or on manual
    // entry (nothing recognized) before it can be saved.
    const [detectedText, setDetectedText] = useState<string | null>(null);
    const [candidates, setCandidates] = useState<TickerCandidate[] | null>(null);
    const [manualOverride, setManualOverride] = useState(false);
    const [manualTicker, setManualTicker] = useState('');
    const [manualMarket, setManualMarket] = useState<Market>('idx');
    const [lastAdded, setLastAdded] = useState<TickerCandidate | null>(null);

    useEffect(() => {
        if (defaultTicker) setViewTicker(defaultTicker);
        if (defaultMarket) setViewMarket(defaultMarket);
    }, [defaultTicker, defaultMarket]);

    useEffect(() => {
        if (!viewTicker) {
            setItems([]);
            return;
        }
        api.fetchManualNews(viewTicker.toUpperCase(), viewMarket)
            .then(setItems)
            .catch(() => setItems([]));
    }, [viewTicker, viewMarket]);

    function resetDraft() {
        setText('');
        setImage(null);
        setDetectedText(null);
        setCandidates(null);
        setManualOverride(false);
        setManualTicker('');
    }

    async function handleAnalyze(e: React.FormEvent) {
        e.preventDefault();
        setError(null);
        setLastAdded(null);
        if (!image && !text.trim()) {
            setError('Ketik teks berita atau upload screenshot dulu.');
            return;
        }

        setSubmitting(true);
        try {
            const result = image ? await api.detectManualNewsImage(image) : await api.detectManualNewsText(text.trim());
            setDetectedText(result.text);
            if (result.candidates.length === 1) {
                await finalize(result.candidates[0], result.text);
            } else {
                setCandidates(result.candidates);
            }
        } catch (err) {
            setError(err instanceof ApiRequestError ? err.message : 'Gagal menganalisis berita.');
        } finally {
            setSubmitting(false);
        }
    }

    async function finalize(candidate: TickerCandidate, resolvedText: string) {
        setSubmitting(true);
        setError(null);
        try {
            await api.submitManualNewsText(candidate.ticker, candidate.market, resolvedText);
            setLastAdded(candidate);
            resetDraft();
            if (candidate.ticker === viewTicker.toUpperCase() && candidate.market === viewMarket) {
                const refreshed = await api.fetchManualNews(viewTicker.toUpperCase(), viewMarket);
                setItems(refreshed);
            }
        } catch (err) {
            setError(err instanceof ApiRequestError ? err.message : 'Gagal menyimpan berita.');
        } finally {
            setSubmitting(false);
        }
    }

    async function handleManualSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!manualTicker.trim() || detectedText === null) {
            setError('Isi ticker dulu, mis. BBCA.');
            return;
        }
        await finalize({ ticker: manualTicker.trim().toUpperCase(), market: manualMarket, name: null }, detectedText);
    }

    async function handleRemove(id: number) {
        await api.removeManualNews(id);
        setItems((prev) => prev.filter((i) => i.id !== id));
    }

    const awaitingResolution = candidates !== null && !lastAdded;

    return (
        <Panel title="Input Berita Manual" icon={<NewsIcon className="h-4 w-4 text-slate-700" />}>
            <p className="mb-3 text-xs text-slate-500">
                Ada berita yang tidak ke-detect otomatis (misal dari Stockbit atau aplikasi lain)?
                Ketik atau upload screenshot-nya — <strong>tidak perlu pilih ticker dulu</strong>,
                aplikasi ini yang mencari saham mana yang dimaksud dari isi beritanya, dicocokkan ke
                watchlist/daftar saham yang sudah dikenal. Sentimennya dianalisis pakai kamus yang
                sama dengan berita otomatis, lalu ikut memengaruhi sub-skor berita saham terkait
                selama 14 hari ke depan.
            </p>

            {!awaitingResolution && (
                <form onSubmit={handleAnalyze} className="flex flex-col gap-2">
                    <textarea
                        value={text}
                        onChange={(e) => {
                            setText(e.target.value);
                            if (e.target.value) setImage(null);
                        }}
                        placeholder="Ketik atau tempel teks berita di sini..."
                        rows={3}
                        className="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-800 placeholder:text-slate-500"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="cursor-pointer rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-700 transition-colors hover:border-slate-400">
                            {image ? image.name : 'Upload screenshot'}
                            <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={(e) => {
                                    const f = e.target.files?.[0] ?? null;
                                    setImage(f);
                                    if (f) setText('');
                                }}
                            />
                        </label>
                        {image && (
                            <button
                                type="button"
                                onClick={() => setImage(null)}
                                className="text-xs text-slate-500 hover:text-slate-700"
                            >
                                Batal
                            </button>
                        )}
                        <button
                            type="submit"
                            disabled={submitting}
                            className="ml-auto rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800 transition-transform hover:scale-105 active:scale-95 disabled:opacity-50 disabled:hover:scale-100"
                        >
                            {submitting ? 'Menganalisis...' : 'Analisis'}
                        </button>
                    </div>
                </form>
            )}

            {awaitingResolution && candidates!.length > 1 && (
                <div className="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p className="text-xs text-slate-600">
                        Berita ini sepertinya menyebut beberapa saham. Ini tentang saham yang mana?
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {candidates!.map((c) => (
                            <button
                                key={`${c.market}:${c.ticker}`}
                                type="button"
                                disabled={submitting}
                                onClick={() => finalize(c, detectedText!)}
                                className="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-800 transition-colors hover:border-slate-500 hover:text-slate-900 disabled:opacity-50"
                            >
                                {candidateLabel(c)}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => setManualOverride((v) => !v)}
                        className="self-start text-xs text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline"
                    >
                        Bukan saham yang dimaksud? Pilih manual
                    </button>
                </div>
            )}

            {awaitingResolution && candidates!.length === 0 && (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    Tidak ada saham yang ke-detect otomatis dari teks ini (belum ada di watchlist/daftar
                    saham yang dikenal). Pilih ticker-nya manual di bawah.
                </p>
            )}

            {awaitingResolution && (candidates!.length === 0 || manualOverride) && (
                <form onSubmit={handleManualSubmit} className="mt-2 flex gap-2">
                    <input
                        type="text"
                        value={manualTicker}
                        onChange={(e) => setManualTicker(e.target.value.toUpperCase())}
                        placeholder="Ticker, mis. BBCA"
                        className="w-32 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-800 placeholder:text-slate-500"
                    />
                    <select
                        value={manualMarket}
                        onChange={(e) => setManualMarket(e.target.value as Market)}
                        className="rounded-xl border border-slate-300 bg-slate-50 px-2 py-2 text-sm text-slate-700"
                    >
                        <option value="idx" className="bg-white text-slate-800">
                            IDX
                        </option>
                        <option value="global" className="bg-white text-slate-800">
                            Global
                        </option>
                    </select>
                    <button
                        type="submit"
                        disabled={submitting}
                        className="rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800 disabled:opacity-50"
                    >
                        Simpan
                    </button>
                </form>
            )}

            {awaitingResolution && (
                <button
                    type="button"
                    onClick={resetDraft}
                    className="mt-2 text-xs text-slate-500 hover:text-slate-700"
                >
                    Batal, tulis ulang
                </button>
            )}

            {error && <p className="mt-2 text-xs text-rose-600">{error}</p>}

            {lastAdded && (
                <p className="mt-2 text-xs text-emerald-600">
                    Ditambahkan ke {candidateLabel(lastAdded)}.
                </p>
            )}

            {items.length > 0 && (
                <ul className="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-3">
                    {items.map((item) => {
                        const s = sentimentLabel(item.sentimentScore);
                        const keywords = [...item.matchedKeywords.positive, ...item.matchedKeywords.negative];

                        return (
                            <li
                                key={item.id}
                                className="animate-row-in rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm text-slate-800">{item.text}</p>
                                    <button
                                        type="button"
                                        onClick={() => handleRemove(item.id)}
                                        className="shrink-0 rounded-full p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                                        aria-label="Hapus berita ini"
                                    >
                                        <CloseIcon className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <p className={`mt-1 text-xs font-medium ${s.className}`}>
                                    Sentimen: {s.text} ({item.sentimentScore.toFixed(2)})
                                    {item.source === 'screenshot' && (
                                        <span className="font-normal text-slate-500"> · dari screenshot</span>
                                    )}
                                </p>
                                {keywords.length > 0 && (
                                    <p className="mt-1 text-[11px] text-slate-500">Kata kunci: {keywords.join(', ')}</p>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}
