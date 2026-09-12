import { useMemo, useState } from 'react';
import type { Market, WatchlistItem } from '@/types';
import { SECTORS, DEFAULT_SECTOR } from '@/lib/sectors';
import Panel from './Panel';
import { StarIcon, CloseIcon } from '@/Components/Icons';

interface WatchlistPanelProps {
    items: WatchlistItem[];
    onSelect: (ticker: string, market: Market) => void;
    onRemove: (id: number) => void;
    onToggleFavorite: (id: number, next: boolean) => void;
    onChangeSector: (id: number, sector: string) => void;
}

export default function WatchlistPanel({
    items,
    onSelect,
    onRemove,
    onToggleFavorite,
    onChangeSector,
}: WatchlistPanelProps) {
    const [favoritesOnly, setFavoritesOnly] = useState(false);

    const visibleItems = favoritesOnly ? items.filter((item) => item.is_favorite) : items;

    const groups = useMemo(() => {
        const bySector = new Map<string, WatchlistItem[]>();
        for (const item of visibleItems) {
            const key = item.sector || DEFAULT_SECTOR;
            if (!bySector.has(key)) bySector.set(key, []);
            bySector.get(key)!.push(item);
        }

        return SECTORS.filter((sector) => bySector.has(sector)).map((sector) => ({
            sector,
            items: bySector.get(sector)!,
        }));
    }, [visibleItems]);

    return (
        <Panel
            title="Watchlist"
            icon={<StarIcon className="h-4 w-4 text-amber-300" filled />}
            right={
                <button
                    type="button"
                    onClick={() => setFavoritesOnly((v) => !v)}
                    className={`rounded-full border px-2.5 py-1 text-[11px] font-medium whitespace-nowrap transition-colors ${
                        favoritesOnly
                            ? 'border-amber-400/40 bg-amber-400/10 text-amber-300'
                            : 'border-white/10 bg-white/5 text-slate-400 hover:border-white/20'
                    }`}
                    title="Tampilkan hanya saham yang ditandai favorit (sudah dibeli)"
                >
                    {favoritesOnly ? 'Favorit saja' : 'Semua'}
                </button>
            }
        >
            {items.length === 0 ? (
                <p className="text-sm text-slate-500">Belum ada saham di watchlist.</p>
            ) : visibleItems.length === 0 ? (
                <p className="text-sm text-slate-500">Belum ada yang ditandai favorit (yang sudah kamu beli).</p>
            ) : (
                <div className="flex flex-col gap-4">
                    {groups.map(({ sector, items: sectorItems }) => (
                        <div key={sector}>
                            <h4 className="mb-1.5 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                {sector} <span className="text-slate-600">({sectorItems.length})</span>
                            </h4>
                            <ul className="flex flex-col gap-2">
                                {sectorItems.map((item, i) => (
                                    <li
                                        key={item.id}
                                        className="animate-row-in flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 transition-colors hover:border-white/20"
                                        style={{ animationDelay: `${i * 50}ms` }}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => onToggleFavorite(item.id, !item.is_favorite)}
                                            className={`shrink-0 rounded-full p-1 transition-colors hover:bg-white/10 ${
                                                item.is_favorite ? 'text-amber-300' : 'text-slate-500 hover:text-slate-300'
                                            }`}
                                            aria-label={
                                                item.is_favorite
                                                    ? 'Hapus tanda favorit'
                                                    : 'Tandai sebagai favorit (sudah dibeli)'
                                            }
                                            title="Favorit = sudah dibeli, dipantau lebih ketat"
                                        >
                                            <StarIcon className="h-3.5 w-3.5" filled={item.is_favorite} />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => onSelect(item.ticker, item.market)}
                                            className="flex-1 truncate text-left text-sm text-slate-200"
                                        >
                                            {item.name || item.ticker}{' '}
                                            <span className="text-slate-500">({item.ticker})</span>
                                        </button>
                                        <select
                                            value={item.sector || DEFAULT_SECTOR}
                                            onChange={(e) => onChangeSector(item.id, e.target.value)}
                                            className="shrink-0 rounded-lg border border-white/10 bg-white/5 px-1.5 py-1 text-[11px] text-slate-400"
                                            title="Ubah sektor"
                                        >
                                            {SECTORS.map((s) => (
                                                <option key={s} value={s} className="bg-slate-800 text-slate-200">
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            onClick={() => onRemove(item.id)}
                                            className="shrink-0 rounded-full p-1 text-slate-500 transition-colors hover:bg-white/10 hover:text-slate-200"
                                            aria-label="Hapus dari watchlist"
                                        >
                                            <CloseIcon className="h-3.5 w-3.5" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            )}
        </Panel>
    );
}
