import type { ReactNode } from 'react';
import { WifiIcon, WifiOffIcon } from '@/Components/Icons';

interface AppLayoutProps {
    online: boolean;
    children: ReactNode;
}

export default function AppLayout({ online, children }: AppLayoutProps) {
    return (
        <div className="relative min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
            {/* Faint decorative washes, sit behind the cards — much softer than a dark theme's glow
                blobs would be, since a light page needs a quiet background for data to stand out. */}
            <div aria-hidden className="pointer-events-none fixed inset-0 overflow-hidden">
                <div className="animate-blob-float absolute -top-24 -left-20 h-80 w-80 rounded-full bg-cyan-300/25 blur-3xl" />
                <div
                    className="animate-blob-float absolute top-1/3 -right-24 h-96 w-96 rounded-full bg-violet-300/20 blur-3xl"
                    style={{ animationDelay: '4s' }}
                />
                <div
                    className="animate-blob-float absolute bottom-0 left-1/4 h-72 w-72 rounded-full bg-emerald-300/15 blur-3xl"
                    style={{ animationDelay: '9s' }}
                />
            </div>

            <div className="relative mx-auto flex min-h-screen max-w-3xl flex-col px-4 pb-16 sm:px-6">
                <header className="sticky top-0 z-10 -mx-4 mb-6 flex items-center justify-between border-b border-slate-200 bg-slate-50/85 px-4 py-4 backdrop-blur-lg sm:-mx-6 sm:px-6">
                    <h1 className="bg-gradient-to-r from-cyan-600 via-sky-600 to-violet-600 bg-clip-text text-lg font-bold text-transparent">
                        Skor Saham
                    </h1>
                    <span
                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${
                            online
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                : 'animate-pulse-ring border-rose-300 bg-rose-50 text-rose-700'
                        }`}
                    >
                        {online ? <WifiIcon className="h-3.5 w-3.5" /> : <WifiOffIcon className="h-3.5 w-3.5" />}
                        {online ? 'Online' : 'Offline'}
                    </span>
                </header>

                <main className="animate-page-in flex-1">{children}</main>

                <footer className="mt-10 text-center text-xs text-slate-500">
                    Bahan pertimbangan, bukan satu-satunya dasar keputusan investasi/trading.
                </footer>
            </div>
        </div>
    );
}
