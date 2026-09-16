import { useCallback, useEffect, useState } from 'react';
import type {
    AccuracyResponse,
    AnalysisResult,
    CachedAnalysis,
    HistoryEntry,
    IpoListing,
    Market,
    PortfolioResponse,
    ScreenerResponse,
    WatchlistItem,
} from '@/types';
import AppLayout from '@/Components/AppLayout';
import StatCard from '@/Components/dashboard/StatCard';
import SearchForm from '@/Components/dashboard/SearchForm';
import ResultPanel from '@/Components/dashboard/ResultPanel';
import ManualNewsPanel from '@/Components/dashboard/ManualNewsPanel';
import PortfolioPanel from '@/Components/dashboard/PortfolioPanel';
import WatchlistPanel from '@/Components/dashboard/WatchlistPanel';
import ScreenerPanel from '@/Components/dashboard/ScreenerPanel';
import IpoPanel from '@/Components/dashboard/IpoPanel';
import LocalHistoryPanel from '@/Components/dashboard/LocalHistoryPanel';
import AccuracyPanel from '@/Components/dashboard/AccuracyPanel';
import { StarIcon, ChartUpIcon, ClockIcon, BriefcaseIcon } from '@/Components/Icons';
import * as api from '@/lib/api';
import { StockDB } from '@/lib/db';

export default function Dashboard() {
    const [online, setOnline] = useState(() => navigator.onLine);
    const [submitting, setSubmitting] = useState(false);
    const [status, setStatus] = useState<{ message: string; error?: boolean } | null>(null);

    const [result, setResult] = useState<AnalysisResult | null>(null);
    const [resultSavedAt, setResultSavedAt] = useState<number | null>(null);
    const [resultTrend, setResultTrend] = useState<HistoryEntry[]>([]);

    const [watchlist, setWatchlist] = useState<WatchlistItem[]>([]);
    const [addingStarterPack, setAddingStarterPack] = useState(false);
    const [screenerMarket, setScreenerMarket] = useState<Market>('idx');
    const [screener, setScreener] = useState<ScreenerResponse | null>(null);
    const [ipoListings, setIpoListings] = useState<IpoListing[]>([]);
    const [localHistory, setLocalHistory] = useState<CachedAnalysis[]>([]);
    const [accuracy, setAccuracy] = useState<AccuracyResponse | null>(null);
    const [portfolio, setPortfolio] = useState<PortfolioResponse | null>(null);

    useEffect(() => {
        const goOnline = () => setOnline(true);
        const goOffline = () => setOnline(false);
        window.addEventListener('online', goOnline);
        window.addEventListener('offline', goOffline);
        return () => {
            window.removeEventListener('online', goOnline);
            window.removeEventListener('offline', goOffline);
        };
    }, []);

    const refreshWatchlist = useCallback(() => {
        api.fetchWatchlist().then(setWatchlist).catch(() => setWatchlist([]));
    }, []);

    const refreshPortfolio = useCallback(() => {
        api.fetchPortfolio().then(setPortfolio).catch(() => setPortfolio(null));
    }, []);

    const refreshLocalHistory = useCallback(() => {
        StockDB.getAll().then(setLocalHistory).catch(() => setLocalHistory([]));
    }, []);

    useEffect(() => {
        refreshWatchlist();
        refreshLocalHistory();
        refreshPortfolio();
        api.fetchIpoListings().then(setIpoListings).catch(() => setIpoListings([]));
        api.fetchAccuracy().then(setAccuracy).catch(() => setAccuracy(null));
    }, [refreshWatchlist, refreshLocalHistory, refreshPortfolio]);

    useEffect(() => {
        api.fetchScreener(screenerMarket).then(setScreener).catch(() => setScreener(null));
    }, [screenerMarket]);

    const showResult = useCallback((analysis: AnalysisResult, savedAt: number | null) => {
        setResult(analysis);
        setResultSavedAt(savedAt);
        api.fetchHistory({ ticker: analysis.ticker, market: analysis.market })
            .then(setResultTrend)
            .catch(() => setResultTrend([]));
    }, []);

    const runAnalyze = useCallback(
        async (ticker: string, market: Market) => {
            setSubmitting(true);
            setStatus({ message: `Menganalisis ${ticker.toUpperCase()}...` });

            try {
                const analysis = await api.analyze(ticker, market);
                setStatus(null);
                await StockDB.save(analysis);
                showResult(analysis, Date.now());
                refreshLocalHistory();
            } catch (err) {
                const cached = await StockDB.getOne(`${market}:${ticker.toUpperCase()}`);
                if (cached) {
                    setStatus({
                        message: 'Tidak bisa mengambil data terbaru — menampilkan hasil terakhir yang tersimpan.',
                        error: true,
                    });
                    showResult(cached.analysis, cached.savedAt);
                } else {
                    const message = err instanceof Error ? err.message : 'Terjadi kesalahan.';
                    setStatus({ message, error: true });
                }
            } finally {
                setSubmitting(false);
            }
        },
        [refreshLocalHistory, showResult]
    );

    async function handleAddWatchlist(sector: string) {
        if (!result) return;
        await api.addToWatchlist(result.ticker, result.market, result.name, sector);
        refreshWatchlist();
    }

    async function handleRemoveWatchlist(id: number) {
        await api.removeFromWatchlist(id);
        refreshWatchlist();
    }

    async function handleToggleFavorite(id: number, next: boolean) {
        await api.updateWatchlistItem(id, { is_favorite: next });
        refreshWatchlist();
    }

    async function handleChangeSector(id: number, sector: string) {
        await api.updateWatchlistItem(id, { sector });
        refreshWatchlist();
    }

    async function handleUpdateHolding(id: number, patch: { shares_owned?: number | null; avg_buy_price?: number | null }) {
        await api.updateWatchlistItem(id, patch);
        refreshWatchlist();
        refreshPortfolio();
    }

    async function handleAddStarterPack(sector: string) {
        setAddingStarterPack(true);
        try {
            const result = await api.addSectorStarterPack(sector);
            refreshWatchlist();
            const message =
                result.added.length > 0
                    ? `Ditambahkan ${result.added.length} saham sektor ${sector}` +
                      (result.skipped.length > 0 ? ` (${result.skipped.length} sudah ada sebelumnya).` : '.')
                    : `Semua saham starter pack sektor ${sector} sudah ada di watchlist kamu.`;
            setStatus({ message });
        } finally {
            setAddingStarterPack(false);
        }
    }

    function handleSelectCached(cached: CachedAnalysis) {
        showResult(cached.analysis, cached.savedAt);
    }

    const inWatchlist = result
        ? watchlist.some((w) => w.ticker === result.ticker && w.market === result.market)
        : false;

    return (
        <AppLayout online={online}>
            <div className="mb-5">
                <SearchForm onSubmit={runAnalyze} submitting={submitting} />
                <p className="mt-2 text-xs text-slate-500">
                    Alat bantu analisis dari berita, laporan keuangan, dan tren volume —{' '}
                    <strong className="text-slate-600">bukan jaminan prediksi harga.</strong>
                </p>
                {status && (
                    <div
                        className={`animate-fade-in-up mt-2 rounded-xl border px-3 py-2 text-sm ${
                            status.error
                                ? 'border-rose-300 bg-rose-50 text-rose-700'
                                : 'border-slate-200 bg-slate-50 text-slate-700'
                        }`}
                    >
                        {status.message}
                    </div>
                )}
            </div>

            <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard
                    label="Watchlist"
                    value={watchlist.length}
                    gradient="from-cyan-500 to-blue-600"
                    icon={<StarIcon filled className="h-full w-full" />}
                />
                <StatCard
                    label="Potensi Naik"
                    value={screener?.items.length ?? 0}
                    gradient="from-emerald-500 to-teal-600"
                    icon={<ChartUpIcon className="h-full w-full" />}
                    delayMs={60}
                />
                <StatCard
                    label="Riwayat Tersimpan"
                    value={localHistory.length}
                    gradient="from-violet-500 to-fuchsia-600"
                    icon={<ClockIcon className="h-full w-full" />}
                    delayMs={120}
                />
                <StatCard
                    label="IPO Terbaru"
                    value={ipoListings.length}
                    gradient="from-amber-500 to-orange-600"
                    icon={<BriefcaseIcon className="h-full w-full" />}
                    delayMs={180}
                />
            </div>

            <div className="flex flex-col gap-5">
                {result && (
                    <ResultPanel
                        result={result}
                        savedAt={resultSavedAt}
                        inWatchlist={inWatchlist}
                        onAddWatchlist={handleAddWatchlist}
                        trendEntries={resultTrend}
                        accuracy={accuracy}
                    />
                )}

                <ManualNewsPanel defaultTicker={result?.ticker} defaultMarket={result?.market} />

                <PortfolioPanel
                    favorites={watchlist.filter((w) => w.is_favorite)}
                    portfolio={portfolio}
                    onUpdateHolding={handleUpdateHolding}
                />

                <AccuracyPanel data={accuracy} />

                <WatchlistPanel
                    items={watchlist}
                    onSelect={runAnalyze}
                    onRemove={handleRemoveWatchlist}
                    onToggleFavorite={handleToggleFavorite}
                    onChangeSector={handleChangeSector}
                    onAddStarterPack={handleAddStarterPack}
                    addingStarterPack={addingStarterPack}
                />
                <ScreenerPanel
                    market={screenerMarket}
                    onMarketChange={setScreenerMarket}
                    response={screener}
                    onSelect={runAnalyze}
                />
                <IpoPanel items={ipoListings} />
                <LocalHistoryPanel items={localHistory} onSelect={handleSelectCached} />
            </div>
        </AppLayout>
    );
}
