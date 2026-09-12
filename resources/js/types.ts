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
    };
    dataCompleteness: DataCompleteness;
    longterm: HorizonResult;
    trading: HorizonResult;
    disclaimer: string;
}

export interface WatchlistItem {
    id: number;
    ticker: string;
    market: Market;
    name: string | null;
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

export interface AccuracyResponse {
    sampleSize: number;
    accuracy: number | null;
    avgForwardReturnPct: number | null;
    byLabel: AccuracyByLabel[];
    subScoreAccuracy: SubScoreAccuracy[];
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
