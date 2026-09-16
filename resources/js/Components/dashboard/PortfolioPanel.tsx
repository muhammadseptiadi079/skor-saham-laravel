import { useEffect, useState } from 'react';
import type { PortfolioResponse, WatchlistItem } from '@/types';
import Panel from './Panel';
import GlassCard from '@/Components/GlassCard';
import { BriefcaseIcon } from '@/Components/Icons';

interface PortfolioPanelProps {
    favorites: WatchlistItem[];
    portfolio: PortfolioResponse | null;
    onUpdateHolding: (id: number, patch: { shares_owned?: number | null; avg_buy_price?: number | null }) => Promise<void>;
}

function money(v: number, currency: string): string {
    const isIdr = currency === 'IDR';
    const symbol = isIdr ? 'Rp' : '$';
    const abs = Math.abs(v);
    const formatted = abs.toLocaleString('id-ID', { maximumFractionDigits: isIdr ? 0 : 2 });

    return `${v < 0 ? '-' : ''}${symbol}${formatted}`;
}

function pct(v: number): string {
    return `${(v * 100).toFixed(1)}%`;
}

function pnlColor(v: number | null): string {
    if (v === null) return 'text-slate-600';
    if (v > 0) return 'text-emerald-600';
    if (v < 0) return 'text-rose-600';
    return 'text-slate-600';
}

function HoldingRow({
    item,
    holding,
    onUpdateHolding,
}: {
    item: WatchlistItem;
    holding: PortfolioResponse['holdings'][number] | undefined;
    onUpdateHolding: PortfolioPanelProps['onUpdateHolding'];
}) {
    const [shares, setShares] = useState(item.shares_owned?.toString() ?? '');
    const [price, setPrice] = useState(item.avg_buy_price?.toString() ?? '');
    const [saving, setSaving] = useState(false);
    const currency = item.market === 'idx' ? 'IDR' : 'USD';

    async function save() {
        const nextShares = shares === '' ? null : Number(shares);
        const nextPrice = price === '' ? null : Number(price);
        if (nextShares === item.shares_owned && nextPrice === item.avg_buy_price) return;

        setSaving(true);
        try {
            await onUpdateHolding(item.id, { shares_owned: nextShares, avg_buy_price: nextPrice });
        } finally {
            setSaving(false);
        }
    }

    return (
        <li className="animate-row-in rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm text-slate-800">
                    {item.name || item.ticker} <span className="text-slate-500">({item.ticker})</span>
                </span>
                {holding?.priceAsOf && (
                    <span className="text-[10px] text-slate-500">
                        Harga per {new Date(holding.priceAsOf).toLocaleDateString('id-ID')}
                    </span>
                )}
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                <input
                    type="number"
                    min="0"
                    step="any"
                    value={shares}
                    onChange={(e) => setShares(e.target.value)}
                    onBlur={save}
                    placeholder="Jumlah lembar"
                    disabled={saving}
                    className="w-28 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-500"
                />
                <input
                    type="number"
                    min="0"
                    step="any"
                    value={price}
                    onChange={(e) => setPrice(e.target.value)}
                    onBlur={save}
                    placeholder={`Harga beli rata-rata (${currency})`}
                    disabled={saving}
                    className="w-44 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-500"
                />
            </div>

            {holding && (
                <div className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 border-t border-slate-200 pt-2 text-xs sm:grid-cols-4">
                    <div>
                        <div className="text-slate-500">Modal</div>
                        <div className="text-slate-700">{money(holding.costBasis, currency)}</div>
                    </div>
                    <div>
                        <div className="text-slate-500">Nilai Sekarang</div>
                        <div className="text-slate-700">
                            {holding.currentValue !== null ? money(holding.currentValue, currency) : 'Belum ada data harga'}
                        </div>
                    </div>
                    <div>
                        <div className="text-slate-500">Untung/Rugi</div>
                        <div className={`font-semibold ${pnlColor(holding.unrealizedPnl)}`}>
                            {holding.unrealizedPnl !== null ? money(holding.unrealizedPnl, currency) : '—'}
                        </div>
                    </div>
                    <div>
                        <div className="text-slate-500">Persentase</div>
                        <div className={`font-semibold ${pnlColor(holding.unrealizedPnlPct)}`}>
                            {holding.unrealizedPnlPct !== null ? pct(holding.unrealizedPnlPct) : '—'}
                        </div>
                    </div>
                </div>
            )}
        </li>
    );
}

export default function PortfolioPanel({ favorites, portfolio, onUpdateHolding }: PortfolioPanelProps) {
    if (favorites.length === 0) {
        return (
            <Panel title="Portfolio" icon={<BriefcaseIcon className="h-4 w-4 text-slate-700" />}>
                <p className="text-sm text-slate-500">
                    Belum ada saham favorit (yang sudah dibeli). Tandai bintang di panel Watchlist
                    dulu, lalu isi jumlah lembar & harga beli di sini.
                </p>
            </Panel>
        );
    }

    return (
        <Panel title="Portfolio" icon={<BriefcaseIcon className="h-4 w-4 text-slate-700" />}>
            <p className="mb-3 text-xs text-slate-500">
                Isi jumlah lembar & harga beli rata-rata tiap saham favorit untuk lihat untung/rugi
                belum terealisasi. Harga sekarang diambil dari cache analisis terakhir (bukan harga
                real-time) — lihat tanggalnya di tiap baris.
            </p>

            {portfolio && portfolio.summaries.length > 0 && (
                <div className="mb-4 grid gap-2 sm:grid-cols-2">
                    {portfolio.summaries.map((s) => (
                        <GlassCard key={s.market} className="p-3">
                            <div className="flex items-center justify-between text-xs text-slate-600">
                                <span className="font-semibold uppercase">{s.market}</span>
                                <span>
                                    {s.pricedHoldingsCount}/{s.holdingsCount} saham ada harga
                                </span>
                            </div>
                            <div className="mt-1 text-sm text-slate-800">
                                Modal: {money(s.totalCostBasis, s.currency)}
                            </div>
                            <div className="text-sm text-slate-800">
                                Nilai:{' '}
                                {s.totalCurrentValue !== null ? money(s.totalCurrentValue, s.currency) : 'Belum lengkap'}
                            </div>
                            {s.totalUnrealizedPnl !== null && (
                                <div className={`text-sm font-semibold ${pnlColor(s.totalUnrealizedPnl)}`}>
                                    {money(s.totalUnrealizedPnl, s.currency)}
                                    {s.totalUnrealizedPnlPct !== null && ` (${pct(s.totalUnrealizedPnlPct)})`}
                                </div>
                            )}
                        </GlassCard>
                    ))}
                </div>
            )}

            <ul className="flex flex-col gap-2">
                {favorites.map((item) => (
                    <HoldingRow
                        key={item.id}
                        item={item}
                        holding={portfolio?.holdings.find((h) => h.id === item.id)}
                        onUpdateHolding={onUpdateHolding}
                    />
                ))}
            </ul>
        </Panel>
    );
}
