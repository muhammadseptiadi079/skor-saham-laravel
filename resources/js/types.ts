export type Market = 'idx' | 'global';

export interface SubScoreResult {
    score: number | null;
    notes: string[];
}

export interface NewsArticle {
    title: string;
    url: string | null;
    source: string | null;
    publishedAt: string | null;
    sentimentScore?: number;
}

export interface OwnershipTransaction {
    date: string | null;
    insiderName: string;
    role: string;
    type: 'buy' | 'sell' | 'other';
    shares: number;
    pricePerShare: number | null;
    value: number | null;
}

export interface NewsSubScore extends SubScoreResult {
    topArticles: NewsArticle[];
}

export interface OwnershipSubScore extends SubScoreResult {
    transactions: OwnershipTransaction[];
}

export interface HorizonResult {
    score: number | null;
    label: string;
    confidence: string | null;
    confidenceNote: string | null;
}

export interface HorizonAlignment {
    aligned: boolean | null;
    note: string | null;
}

export interface PriceTarget {
    targetPrice: number;
    upsidePct: number;
}

export interface DataCompleteness {
    available: number;
    total: number;
}

export interface AnalysisResult {
    ticker: string;
    market: Market;
    name: string;
    currency: string;
    generatedAt: string;
    subScores: {
        fundamentals: SubScoreResult;
        news: NewsSubScore;
        momentum: SubScoreResult;
        momentumLongTerm: SubScoreResult;
        ownership: OwnershipSubScore;
        // IDX only — always null (score) for global tickers, see IdxForeignFlowService.
        foreignFlow: SubScoreResult;
    };
    dataCompleteness: DataCompleteness;
    lowLiquidity: boolean;
    longterm: HorizonResult;
    trading: HorizonResult;
    horizonAlignment: HorizonAlignment;
    subScoreDivergence: string | null;
    staleDataWarning: string | null;
    marketRegime: 'bull' | 'bear' | 'sideways' | null;
    // Upcoming stock split / rights issue (HMETD) note — IDX only, see IdxCorporateActionService.
    corporateAction: string | null;
    priceTarget: PriceTarget | null;
    disclaimer: string;
}

export interface WatchlistItem {
    id: number;
    ticker: string;
    market: Market;
    name: string | null;
    sector: string;
    is_favorite: boolean;
    shares_owned: number | null;
    avg_buy_price: number | null;
    created_at: string;
    updated_at: string;
}

export interface HistoryEntry {
    id: number;
    ticker: string;
    market: Market;
    name: string | null;
    currency: string | null;
    longterm_score: number | null;
    longterm_label: string | null;
    trading_score: number | null;
    trading_label: string | null;
    sub_scores: AnalysisResult['subScores'] | null;
    generated_at: string;
}

export interface ScreenerItem {
    ticker: string;
    name: string | null;
    market: Market;
    currency: string | null;
    trading_score?: number | null;
    trading_label?: string | null;
    longterm_score?: number | null;
    longterm_label?: string | null;
    generated_at: string;
    /** User-assigned watchlist sector, a SectorStarterPacks fallback guess, or "Lainnya". */
    sector: string;
    /** Historical average forward return for this item's label (trading horizon only, gated by
     * sample size on the backend) — null when there isn't enough graded history to say anything. */
    avgForwardReturnPct: number | null;
}

export interface ScreenerResponse {
    market: Market;
    horizon: 'trading' | 'longterm';
    generatedFrom: string;
    items: ScreenerItem[];
}

export interface IpoListing {
    id: number;
    market: Market;
    ticker: string | null;
    company_name: string;
    ipo_date: string | null;
    price_range: string | null;
    status: string | null;
    source: string;
    fetched_at: string;
}

export interface AccuracyByLabel {
    label: string;
    sampleSize: number;
    correct: number;
    accuracy: number;
    avgForwardReturnPct: number;
}

export interface SubScoreAccuracy {
    subScore: string;
    sampleSize: number;
    directionalAccuracy: number | null;
}

export interface WeightSuggestion {
    subScore: string;
    sampleSize: number;
    directionalAccuracy: number;
    suggestion: string;
}

export interface ConfidenceCalibration {
    status: 'insufficient_data' | 'ok' | 'needs_review';
    tinggiAccuracy: number | null;
    tinggiSampleSize: number;
    rendahAccuracy: number | null;
    rendahSampleSize: number;
    sampleNeededPerLevel: number;
    message: string;
}

export interface AccuracyResponse {
    sampleSize: number;
    accuracy: number | null;
    avgForwardReturnPct: number | null;
    byLabel: AccuracyByLabel[];
    byMarketRegime: AccuracyByLabel[];
    byWatchlistSector: AccuracyByLabel[];
    byConfidence: AccuracyByLabel[];
    confidenceCalibration: ConfidenceCalibration;
    subScoreAccuracy: SubScoreAccuracy[];
    weightSuggestions: WeightSuggestion[];
}

export interface ApiError {
    error: string;
    message: string;
}

// Cached in IndexedDB for offline viewing — mirrors what the old vanilla PWA stored.
export interface CachedAnalysis {
    id: string; // `${market}:${ticker}`
    savedAt: number;
    analysis: AnalysisResult;
}

export interface PortfolioHolding {
    id: number;
    ticker: string;
    market: Market;
    name: string | null;
    sector: string;
    sharesOwned: number;
    avgBuyPrice: number;
    currentPrice: number | null;
    priceAsOf: string | null;
    costBasis: number;
    currentValue: number | null;
    unrealizedPnl: number | null;
    unrealizedPnlPct: number | null;
}

export interface PortfolioSummary {
    market: Market;
    currency: string;
    holdingsCount: number;
    pricedHoldingsCount: number;
    totalCostBasis: number;
    totalCurrentValue: number | null;
    totalUnrealizedPnl: number | null;
    totalUnrealizedPnlPct: number | null;
}

export interface PortfolioResponse {
    holdings: PortfolioHolding[];
    summaries: PortfolioSummary[];
}

export interface ManualNewsItem {
    id: number;
    ticker: string;
    market: Market;
    text: string;
    source: 'typed' | 'screenshot';
    sentimentScore: number;
    matchedKeywords: { positive: string[]; negative: string[] };
    createdAt: string;
}

export interface TickerCandidate {
    ticker: string;
    market: Market;
    name: string | null;
}

export interface ManualNewsDetectResult {
    text: string;
    candidates: TickerCandidate[];
}
