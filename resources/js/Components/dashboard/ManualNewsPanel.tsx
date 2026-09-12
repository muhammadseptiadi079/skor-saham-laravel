import { useEffect, useState } from 'react';
import type { ManualNewsItem, Market } from '@/types';
import Panel from './Panel';
import { NewsIcon, CloseIcon } from '@/Components/Icons';
import * as api from '@/lib/api';
import { ApiRequestError } from '@/lib/api';

interface ManualNewsPanelProps {
    defaultTicker?: string;
    defaultMarket?: Market;
}

function sentimentLabel(score: number): { text: string; className: string } {
    if (score >= 0.5) return { text: 'sangat positif', className: 'text-emerald-400' };
    if (score >= 0.15) return { text: 'positif', className: 'text-emerald-400' };
    if (score > -0.15) return { text: 'netral', className: 'text-slate-400' };
    if (score > -0.5) return { text: 'negatif', className: 'text-rose-400' };
    return { text: 'sangat negatif', className: 'text-rose-400' };
}

export default function ManualNewsPanel({ defaultTicker, defaultMarket }: ManualNewsPanelProps) {
    const [ticker, setTicker] = useState(defaultTicker ?? '');
    const [market, setMarket] = useState<Market>(defaultMarket ?? 'idx');
    const [text, setText] = useState('');
    const [image, setImage] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [items, setItems] = useState<ManualNewsItem[]>([]);

    useEffect(() => {
        if (defaultTicker) setTicker(defaultTicker);
        if (defaultMarket) setMarket(defaultMarket);
    }, [defaultTicker, defaultMarket]);

    useEffect(() => {
        if (!ticker) {
            setItems([]);
            return;
        }
        api.fetchManualNews(ticker.toUpperCase(), market)
            .then(setItems)
            .catch(() => setItems([]));
    }, [ticker, market]);

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!ticker.trim()) {
            setError('Isi ticker dulu, mis. BBCA.');
            return;
        }
        if (!image && !text.trim()) {
            setError('Ketik teks berita atau upload screenshot dulu.');
            return;
        }

        setSubmitting(true);
        setError(null);
        try {
            if (image) {
                await api.submitManualNewsImage(ticker.toUpperCase(), market, image);
            } else {
                await api.submitManualNewsText(ticker.toUpperCase(), market, text.trim());
            }
            setText('');
            setImage(null);
            const refreshed = await api.fetchManualNews(ticker.toUpperCase(), market);
            setItems(refreshed);
        } catch (err) {
            setError(err instanceof ApiRequestError ? err.message : 'Gagal mengirim berita.');
        } finally {
            setSubmitting(false);
        }
    }

    async function handleRemove(id: number) {
        await api.removeManualNews(id);
        setItems((prev) => prev.filter((i) => i.id !== id));
    }

    return (
        <Panel title="Input Berita Manual" icon={<NewsIcon className="h-4 w-4 text-sky-300" />}>
            <p className="mb-3 text-xs text-slate-500">
                Ada berita yang tidak ke-detect otomatis (misal dari Stockbit atau aplikasi lain)?
                Ketik atau upload screenshot-nya — dianalisis pakai kamus sentimen yang sama dengan
                berita otomatis, lalu ikut memengaruhi sub-skor berita saham ini selama 14 hari ke
                depan.
            </p>
            <form onSubmit={handleSubmit} className="flex flex-col gap-2">
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={ticker}
                        onChange={(e) => setTicker(e.target.value.toUpperCase())}
                        placeholder="Ticker, mis. BBCA"
                        className="w-32 rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-sm text-slate-200 placeholder:text-slate-500"
                    />
                    <select
                        value={market}
                        onChange={(e) => setMarket(e.target.value as Market)}
                        className="rounded-xl border border-white/15 bg-white/5 px-2 py-2 text-sm text-slate-300"
                    >
                        <option value="idx" className="bg-slate-800 text-slate-200">
                            IDX
                        </option>
                        <option value="global" className="bg-slate-800 text-slate-200">
                            Global
                        </option>
                    </select>
                </div>
                <textarea
                    value={text}
                    onChange={(e) => {
                        setText(e.target.value);
                        if (e.target.value) setImage(null);
                    }}
                    placeholder="Ketik atau tempel teks berita di sini..."
                    rows={3}
                    className="rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-sm text-slate-200 placeholder:text-slate-500"
                />
                <div className="flex flex-wrap items-center gap-2">
                    <label className="cursor-pointer rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-300 transition-colors hover:border-white/30">
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
                            className="text-xs text-slate-500 hover:text-slate-300"
                        >
                            Batal
                        </button>
                    )}
                    <button
                        type="submit"
                        disabled={submitting}
                        className="ml-auto rounded-xl border border-sky-400/30 bg-sky-400/10 px-3 py-2 text-sm font-medium text-sky-300 transition-transform hover:scale-105 active:scale-95 disabled:opacity-50 disabled:hover:scale-100"
                    >
                        {submitting ? 'Menganalisis...' : 'Analisis'}
                    </button>
                </div>
                {error && <p className="text-xs text-rose-400">{error}</p>}
            </form>

            {items.length > 0 && (
                <ul className="mt-4 flex flex-col gap-2 border-t border-white/10 pt-3">
                    {items.map((item) => {
                        const s = sentimentLabel(item.sentimentScore);
                        const keywords = [...item.matchedKeywords.positive, ...item.matchedKeywords.negative];

                        return (
                            <li
                                key={item.id}
                                className="animate-row-in rounded-xl border border-white/10 bg-white/5 px-3 py-2"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm text-slate-200">{item.text}</p>
                                    <button
                                        type="button"
                                        onClick={() => handleRemove(item.id)}
                                        className="shrink-0 rounded-full p-1 text-slate-500 transition-colors hover:bg-white/10 hover:text-slate-200"
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
