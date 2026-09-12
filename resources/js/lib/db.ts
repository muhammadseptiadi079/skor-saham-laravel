// Minimal IndexedDB wrapper to keep analysis history on the device for offline viewing.
// TypeScript port of the old vanilla PWA's db.js — same schema, so existing installs keep
// their cached history after the frontend rewrite.
import type { AnalysisResult, CachedAnalysis } from '@/types';

const DB_NAME = 'skor-saham-db';
const STORE = 'history';

let dbPromise: Promise<IDBDatabase> | null = null;

function open(): Promise<IDBDatabase> {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'id' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
    return dbPromise;
}

export async function save(analysis: AnalysisResult): Promise<CachedAnalysis> {
    const db = await open();
    const id = `${analysis.market}:${analysis.ticker}`;
    const record: CachedAnalysis = { id, savedAt: Date.now(), analysis };
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).put(record);
        tx.oncomplete = () => resolve(record);
        tx.onerror = () => reject(tx.error);
    });
}

export async function getAll(): Promise<CachedAnalysis[]> {
    const db = await open();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).getAll();
        req.onsuccess = () => resolve((req.result as CachedAnalysis[]).sort((a, b) => b.savedAt - a.savedAt));
        req.onerror = () => reject(req.error);
    });
}

export async function getOne(id: string): Promise<CachedAnalysis | null> {
    const db = await open();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).get(id);
        req.onsuccess = () => resolve((req.result as CachedAnalysis) || null);
        req.onerror = () => reject(req.error);
    });
}

export const StockDB = { save, getAll, getOne };
