import type { CachedAnalysis } from '@/types';
import Panel from './Panel';
import { ClockIcon } from '@/Components/Icons';

interface LocalHistoryPanelProps {
    items: CachedAnalysis[];
    onSelect: (cached: CachedAnalysis) => void;
}

export default function LocalHistoryPanel({ items, onSelect }: LocalHistoryPanelProps) {
    return (
        <Panel title="Riwayat (tersimpan di HP)" icon={<ClockIcon className="h-4 w-4 text-violet-300" />}>
            {items.length === 0 ? (
                <p className="text-sm text-slate-500">Belum ada riwayat tersimpan di perangkat ini.</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {items.map((item, i) => (
                        <li key={item.id} className="animate-row-in" style={{ animationDelay: `${i * 50}ms` }}>
                            <button
                                type="button"
                                onClick={() => onSelect(item)}
                                className="flex w-full items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left transition-colors hover:border-white/20"
                            >
                                <span className="truncate text-sm text-slate-200">
                                    {item.analysis.name} <span className="text-slate-500">({item.analysis.ticker})</span>
                                </span>
                                <span className="text-xs whitespace-nowrap text-slate-500">
                                    {new Date(item.savedAt).toLocaleDateString('id-ID')}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
