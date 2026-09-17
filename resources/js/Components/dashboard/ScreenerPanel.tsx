import type { Market, ScreenerResponse } from '@/types';
import Panel from './Panel';
import EmptyState from './EmptyState';
import { ChartUpIcon } from '@/Components/Icons';
import { textClassForScore } from '@/Components/charts/scoreColors';

interface ScreenerPanelProps {
    market: Market;
    onMarketChange: (market: Market) => void;
    response: ScreenerResponse | null;
    onSelect: (ticker: string, market: Market) => void;
}

export default function ScreenerPanel({ market, onMarketChange, response, onSelect }: ScreenerPanelProps) {
    const items = response?.items ?? [];

    return (
        <Panel
            title="Potensi Naik (Screener)"
            icon={<ChartUpIcon className="h-4 w-4 text-slate-700" />}
            right={
                <select
                    value={market}
                    onChange={(e) => onMarketChange(e.target.value as Market)}
                    className="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-800"
                >
                    <option value="idx" className="bg-white">
                        IDX
                    </option>
                    <option value="global" className="bg-white">
                        Global
                    </option>
                </select>
            }
        >
            <p className="mb-3 text-xs text-slate-500">
                Berdasarkan skor cache dari watchlist &amp; daftar kurasi (LQ45/blue-chip), diperbarui berkala —
                bukan hasil hitung langsung saat ini juga.
            </p>
            {items.length === 0 ? (
                <EmptyState
                    icon={<ChartUpIcon className="h-5 w-5" />}
                    message={
                        <>
                            Belum ada data screener. Jalankan{' '}
                            <code className="rounded bg-slate-100 px-1 py-0.5 text-xs">php artisan stocks:refresh-scores</code>{' '}
                            di server dulu.
                        </>
                    }
                />
            ) : (
                <ul className="flex flex-col gap-2">
                    {items.map((item, i) => (
                        <li
                            key={item.ticker}
                            className="animate-row-in"
                            style={{ animationDelay: `${i * 50}ms` }}
                        >
                            <button
                                type="button"
                                onClick={() => onSelect(item.ticker, item.market)}
                                className="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left transition-colors hover:border-slate-300"
                            >
                                <span className="truncate text-sm text-slate-800">
                                    {item.name || item.ticker} <span className="text-slate-500">({item.ticker})</span>
                                </span>
                                <span className={`text-xs font-semibold whitespace-nowrap ${textClassForScore(item.trading_score ?? item.longterm_score)}`}>
                                    {item.trading_label || item.longterm_label}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
