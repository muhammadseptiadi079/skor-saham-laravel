import { useState, type FormEvent } from 'react';
import type { Market } from '@/types';
import { SearchIcon, SpinnerIcon, ChartUpIcon } from '@/Components/Icons';

interface SearchFormProps {
    onSubmit: (ticker: string, market: Market) => void;
    submitting: boolean;
    /** Jumps to the Screener tab's trading list — a shortcut for "show me candidates with an
     * estimated % gain, grouped by sector" without analyzing one ticker at a time. */
    onShowTrading: () => void;
}

export default function SearchForm({ onSubmit, submitting, onShowTrading }: SearchFormProps) {
    const [ticker, setTicker] = useState('');
    const [market, setMarket] = useState<Market>('idx');

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const trimmed = ticker.trim();
        if (!trimmed) return;
        onSubmit(trimmed, market);
    }

    return (
        <form onSubmit={handleSubmit} className="flex flex-wrap gap-2">
            <select
                value={market}
                onChange={(e) => setMarket(e.target.value as Market)}
                className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 backdrop-blur-xl focus:border-slate-500 focus:outline-none"
            >
                <option value="idx" className="bg-white">
                    IDX (Indonesia)
                </option>
                <option value="global" className="bg-white">
                    Global (US, dll)
                </option>
            </select>
            <input
                value={ticker}
                onChange={(e) => setTicker(e.target.value)}
                type="text"
                placeholder="Contoh: BBCA atau AAPL"
                autoComplete="off"
                className="min-w-36 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-500 backdrop-blur-xl focus:border-slate-500 focus:outline-none"
            />
            <button
                type="submit"
                disabled={submitting}
                className="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-neutral-800 to-black px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-black/30 transition-transform hover:scale-105 active:scale-95 disabled:opacity-60 disabled:hover:scale-100"
            >
                {submitting ? <SpinnerIcon className="h-4 w-4 animate-spin" /> : <SearchIcon className="h-4 w-4" />}
                {submitting ? 'Menganalisis...' : 'Analisis'}
            </button>
            <button
                type="button"
                onClick={onShowTrading}
                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-800 transition-transform hover:scale-105 hover:border-slate-400 active:scale-95"
                title="Lihat daftar saham per sektor dengan perkiraan persentase kenaikan trading, tandai favorit langsung dari situ"
            >
                <ChartUpIcon className="h-4 w-4" />
                Trading
            </button>
        </form>
    );
}
