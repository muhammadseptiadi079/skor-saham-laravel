// Thin typed fetch wrappers around the existing /api/* JSON endpoints (unchanged by the
// frontend rewrite — see app/Http/Controllers). Plain fetch rather than Inertia visits, since
// these are live-search/panel interactions, not full page navigations.
import type {
    AccuracyResponse,
    AnalysisResult,
    ApiError,
    HistoryEntry,
    IpoListing,
    ManualNewsDetectResult,
    ManualNewsItem,
    Market,
    PortfolioResponse,
    ScreenerResponse,
    WatchlistItem,
} from '@/types';

export class ApiRequestError extends Error {
    constructor(
        message: string,
        public readonly payload: ApiError | null,
        public readonly status: number
    ) {
        super(message);
    }
}

async function asJson<T>(res: Response): Promise<T> {
    const data = await res.json().catch(() => null);
    if (!res.ok) {
        throw new ApiRequestError((data as ApiError | null)?.message ?? 'Terjadi kesalahan.', data, res.status);
    }
    return data as T;
}

export function analyze(ticker: string, market: Market): Promise<AnalysisResult> {
    const params = new URLSearchParams({ ticker, market });
    return fetch(`/api/analyze?${params}`).then((res) => asJson<AnalysisResult>(res));
}

export function fetchWatchlist(): Promise<WatchlistItem[]> {
    return fetch('/api/watchlist').then((res) => asJson<WatchlistItem[]>(res));
}

export function addToWatchlist(
    ticker: string,
    market: Market,
    name?: string | null,
    sector?: string
): Promise<WatchlistItem> {
    return fetch('/api/watchlist', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticker, market, name, sector }),
    }).then((res) => asJson<WatchlistItem>(res));
}

export function updateWatchlistItem(
    id: number,
    patch: Partial<Pick<WatchlistItem, 'sector' | 'is_favorite' | 'shares_owned' | 'avg_buy_price'>>
): Promise<WatchlistItem> {
    return fetch(`/api/watchlist/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(patch),
    }).then((res) => asJson<WatchlistItem>(res));
}

export function removeFromWatchlist(id: number): Promise<void> {
    return fetch(`/api/watchlist/${id}`, { method: 'DELETE' }).then(() => undefined);
}

export interface StarterPackResult {
    added: WatchlistItem[];
    skipped: string[];
}

export function addSectorStarterPack(sector: string): Promise<StarterPackResult> {
    return fetch('/api/watchlist/starter-pack', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sector }),
    }).then((res) => asJson<StarterPackResult>(res));
}

export function fetchHistory(params?: { ticker?: string; market?: Market }): Promise<HistoryEntry[]> {
    const qs = new URLSearchParams();
    if (params?.ticker) qs.set('ticker', params.ticker);
    if (params?.market) qs.set('market', params.market);
    const suffix = qs.toString() ? `?${qs}` : '';
    return fetch(`/api/history${suffix}`).then((res) => asJson<HistoryEntry[]>(res));
}

export function fetchScreener(market: Market, horizon: 'trading' | 'longterm' = 'trading'): Promise<ScreenerResponse> {
    const params = new URLSearchParams({ market, horizon });
    return fetch(`/api/screener?${params}`).then((res) => asJson<ScreenerResponse>(res));
}

export function fetchIpoListings(market?: Market): Promise<IpoListing[]> {
    const suffix = market ? `?market=${market}` : '';
    return fetch(`/api/ipo${suffix}`).then((res) => asJson<IpoListing[]>(res));
}

export function fetchAccuracy(market?: Market): Promise<AccuracyResponse> {
    const suffix = market ? `?market=${market}` : '';
    return fetch(`/api/accuracy${suffix}`).then((res) => asJson<AccuracyResponse>(res));
}

export function fetchManualNews(ticker: string, market: Market): Promise<ManualNewsItem[]> {
    const params = new URLSearchParams({ ticker, market });
    return fetch(`/api/news/manual?${params}`).then((res) => asJson<ManualNewsItem[]>(res));
}

export function submitManualNewsText(ticker: string, market: Market, text: string): Promise<ManualNewsItem> {
    return fetch('/api/news/manual', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticker, market, text }),
    }).then((res) => asJson<ManualNewsItem>(res));
}

// Resolves which stock(s) a piece of news is about *before* the caller needs to know a ticker —
// OCRs a screenshot if one is given, then matches the resulting text against every ticker/company
// name this app already knows about. See TickerDetectionService for how matching works.
export function detectManualNewsText(text: string): Promise<ManualNewsDetectResult> {
    return fetch('/api/news/manual/detect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text }),
    }).then((res) => asJson<ManualNewsDetectResult>(res));
}

export function detectManualNewsImage(image: File): Promise<ManualNewsDetectResult> {
    const form = new FormData();
    form.set('image', image);
    return fetch('/api/news/manual/detect', { method: 'POST', body: form }).then((res) => asJson<ManualNewsDetectResult>(res));
}

export function removeManualNews(id: number): Promise<void> {
    return fetch(`/api/news/manual/${id}`, { method: 'DELETE' }).then(() => undefined);
}

export function fetchPortfolio(market?: Market): Promise<PortfolioResponse> {
    const suffix = market ? `?market=${market}` : '';
    return fetch(`/api/portfolio${suffix}`).then((res) => asJson<PortfolioResponse>(res));
}
