import { useMemo, useState } from 'react';
import type { Market, ScreenerItem, ScreenerResponse, WatchlistItem } from '@/types';
import { SECTORS, DEFAULT_SECTOR } from '@/lib/sectors';
import Panel from './Panel';
import EmptyState from './EmptyState';
import { ChartUpIcon, StarIcon } from '@/Components/Icons';
import { textClassForScore } from '@/Components/charts/scoreColors';

interface ScreenerPanelProps {
    market: Market;
    onMarketChange: (market: Market) => void;
    response: ScreenerResponse | null;
    onSelect: (ticker: string, market: Market) => void;
    watchlist: WatchlistItem[];
    onQuickFavorite: (ticker: string, market: Market, name: string | null) => Promise<void>;
}

function pctReturn(v: number): string {
    return `${v >= 0 ? '+' : ''}${v.toFixed(1)}%`;
}

export default function ScreenerPanel({ market, onMarketChange, response, onSelect, watchlist, onQuickFavorite }: ScreenerPanelProps) {
    const items = response?.items ?? [];
    const [markingTicker, setMarkingTicker] = useState<string | null>(null);

    const groups = useMemo(() => {
        const bySector = new Map<string, ScreenerItem[]>();
        for (const item of items) {
            const key = item.sector || DEFAULT_SECTOR;
            if (!bySector.has(key)) bySector.set(key, []);
            bySector.get(key)!.push(item);
        }

        return SECTORS.filter((sector) => bySector.has(sector)).map((sector) => ({
            sector,
            items: bySector.get(sector)!,
        }));
    }, [items]);

    function isFavorite(item: ScreenerItem): boolean {
        return watchlist.some((w) => w.ticker === item.ticker && w.market === item.market && w.is_favorite);
    }

    async function handleStarClick(item: ScreenerItem) {
        setMarkingTicker(item.ticker);
        try {
            await onQuickFavorite(item.ticker, item.market, item.name);
        } finally {
            setMarkingTicker(null);
        }
    }

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
                bukan hasil hitung langsung saat ini juga. Persentase di samping tiap saham adalah rata-rata
                pergerakan historis untuk label yang sama (bukan prediksi khusus saham itu), muncul kalau sudah
                ada cukup sampel.
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
                <div className="flex flex-col gap-4">
                    {groups.map(({ sector, items: sectorItems }) => (
                        <div key={sector}>
                            <p className="mb-1.5 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                {sector} ({sectorItems.length})
                            </p>
                            <ul className="flex flex-col gap-2">
                                {sectorItems.map((item, i) => {
                                    const favorite = isFavorite(item);

                                    return (
                                        <li
                                            key={`${item.market}:${item.ticker}`}
                                            className="animate-row-in flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                                            style={{ animationDelay: `${i * 50}ms` }}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => onSelect(item.ticker, item.market)}
                                                className="flex min-w-0 flex-1 items-center justify-between gap-2 text-left"
                                            >
                                                <span className="truncate text-sm text-slate-800">
                                                    {item.name || item.ticker} <span className="text-slate-500">({item.ticker})</span>
                                                </span>
                                                <span className="flex shrink-0 flex-col items-end">
                                                    <span
                                                        className={`text-xs font-semibold whitespace-nowrap ${textClassForScore(item.trading_score ?? item.longterm_score)}`}
                                                    >
                                                        {item.trading_label || item.longterm_label}
                                                    </span>
                                                    {item.avgForwardReturnPct !== null && (
                                                        <span
                                                            className={`text-[11px] font-bold whitespace-nowrap ${item.avgForwardReturnPct >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}
                                                        >
                                                            {pctReturn(item.avgForwardReturnPct)}
                                                        </span>
                                                    )}
                                                </span>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleStarClick(item)}
                                                disabled={favorite || markingTicker === item.ticker}
                                                className={`shrink-0 rounded-full p-1.5 transition-colors ${
                                                    favorite ? 'text-black' : 'text-slate-400 hover:text-slate-700'
                                                } disabled:cursor-default`}
                                                title={favorite ? 'Sudah ditandai favorit' : 'Tambah ke watchlist & tandai favorit'}
                                            >
                                                <StarIcon filled={favorite} className="h-4 w-4" />
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </div>
            )}
        </Panel>
    );
}
