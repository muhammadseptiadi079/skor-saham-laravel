import type { IpoListing } from '@/types';
import Panel from './Panel';
import EmptyState from './EmptyState';
import { BriefcaseIcon } from '@/Components/Icons';

function formatIpoDate(date: string | null): string {
    if (!date) return '';
    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return date;
    return parsed.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function IpoPanel({ items }: { items: IpoListing[] }) {
    return (
        <Panel title="IPO Terbaru" icon={<BriefcaseIcon className="h-4 w-4 text-slate-700" />}>
            {items.length === 0 ? (
                <EmptyState
                    icon={<BriefcaseIcon className="h-5 w-5" />}
                    message={
                        <>
                            Belum ada data IPO. Jalankan{' '}
                            <code className="rounded bg-slate-100 px-1 py-0.5 text-xs">php artisan stocks:refresh-ipo</code>{' '}
                            di server dulu.
                        </>
                    }
                />
            ) : (
                <ul className="flex flex-col gap-2">
                    {items.map((item, i) => (
                        <li
                            key={item.id}
                            className="animate-row-in flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                            style={{ animationDelay: `${i * 50}ms` }}
                        >
                            <span className="truncate text-sm text-slate-800">
                                {item.company_name}
                                {item.ticker ? ` (${item.ticker})` : ''}{' '}
                                <span className="text-slate-500">— {item.market === 'idx' ? 'IDX' : 'Global'}</span>
                            </span>
                            <span className="text-xs whitespace-nowrap text-slate-600">{formatIpoDate(item.ipo_date)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
