import type { Market, WatchlistItem } from '@/types';
import Panel from './Panel';
import { StarIcon, CloseIcon } from '@/Components/Icons';

interface WatchlistPanelProps {
    items: WatchlistItem[];
    onSelect: (ticker: string, market: Market) => void;
    onRemove: (id: number) => void;
}

export default function WatchlistPanel({ items, onSelect, onRemove }: WatchlistPanelProps) {
    return (
        <Panel title="Watchlist" icon={<StarIcon className="h-4 w-4 text-amber-300" filled />}>
            {items.length === 0 ? (
                <p className="text-sm text-slate-500">Belum ada saham di watchlist.</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {items.map((item, i) => (
                        <li
                            key={item.id}
                            className="animate-row-in flex items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 transition-colors hover:border-white/20"
                            style={{ animationDelay: `${i * 50}ms` }}
                        >
                            <button
                                type="button"
                                onClick={() => onSelect(item.ticker, item.market)}
                                className="flex-1 truncate text-left text-sm text-slate-200"
                            >
                                {item.name || item.ticker} <span className="text-slate-500">({item.ticker})</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => onRemove(item.id)}
                                className="rounded-full p-1 text-slate-500 transition-colors hover:bg-white/10 hover:text-slate-200"
                                aria-label="Hapus dari watchlist"
                            >
                                <CloseIcon className="h-3.5 w-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
