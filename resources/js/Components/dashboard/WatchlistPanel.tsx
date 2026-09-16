import { useMemo, useState } from 'react';
import type { Market, WatchlistItem } from '@/types';
import { SECTORS, DEFAULT_SECTOR } from '@/lib/sectors';
import Panel from './Panel';
import { StarIcon, CloseIcon } from '@/Components/Icons';

const STARTER_PACK_SECTORS = SECTORS.filter((s) => s !== DEFAULT_SECTOR);

interface WatchlistPanelProps {
    items: WatchlistItem[];
    onSelect: (ticker: string, market: Market) => void;
    onRemove: (id: number) => void;
    onToggleFavorite: (id: number, next: boolean) => void;
    onChangeSector: (id: number, sector: string) => void;
    onAddStarterPack: (sector: string) => void;
    addingStarterPack: boolean;
}

export default function WatchlistPanel({
    items,
    onSelect,
    onRemove,
    onToggleFavorite,
    onChangeSector,
    onAddStarterPack,
    addingStarterPack,
}: WatchlistPanelProps) {
    const [favoritesOnly, setFavoritesOnly] = useState(false);
    const [starterSector, setStarterSector] = useState<string>(STARTER_PACK_SECTORS[0]);

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
            icon={<StarIcon className="h-4 w-4 text-slate-900" filled />}
            right={
                <button
                    type="button"
                    onClick={() => setFavoritesOnly((v) => !v)}
                    className={`rounded-full border px-2.5 py-1 text-[11px] font-medium whitespace-nowrap transition-colors ${
                        favoritesOnly
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300'
                    }`}
                    title="Tampilkan hanya saham yang ditandai favorit (sudah dibeli)"
                >
                    {favoritesOnly ? 'Favorit saja' : 'Semua'}
                </button>
            }
        >
            <div className="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                <span className="text-xs text-slate-600">Kurang pilihan di suatu sektor?</span>
                <select
                    value={starterSector}
                    onChange={(e) => setStarterSector(e.target.value)}
                    className="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700"
                >
                    {STARTER_PACK_SECTORS.map((s) => (
                        <option key={s} value={s} className="bg-white text-slate-800">
                            {s}
                        </option>
                    ))}
                </select>
                <button
                    type="button"
                    onClick={() => onAddStarterPack(starterSector)}
                    disabled={addingStarterPack}
                    className="rounded-lg border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-800 transition-colors hover:border-slate-500 disabled:opacity-50"
                    title="Tambahkan sekumpulan saham IDX terkenal di sektor ini ke watchlist"
                >
                    {addingStarterPack ? 'Menambahkan...' : '+ Tambah starter pack'}
                </button>
            </div>

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
                                        className="animate-row-in flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 transition-colors hover:border-slate-300"
                                        style={{ animationDelay: `${i * 50}ms` }}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => onToggleFavorite(item.id, !item.is_favorite)}
                                            className={`shrink-0 rounded-full p-1 transition-colors hover:bg-slate-100 ${
                                                item.is_favorite ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'
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
                                            className="flex-1 truncate text-left text-sm text-slate-800"
                                        >
                                            {item.name || item.ticker}{' '}
                                            <span className="text-slate-500">({item.ticker})</span>
                                        </button>
                                        <select
                                            value={item.sector || DEFAULT_SECTOR}
                                            onChange={(e) => onChangeSector(item.id, e.target.value)}
                                            className="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-1 text-[11px] text-slate-600"
                                            title="Ubah sektor"
                                        >
                                            {SECTORS.map((s) => (
                                                <option key={s} value={s} className="bg-white text-slate-800">
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            onClick={() => onRemove(item.id)}
                                            className="shrink-0 rounded-full p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
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
