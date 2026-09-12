import type { ReactNode } from 'react';
import { WifiIcon, WifiOffIcon } from '@/Components/Icons';

interface AppLayoutProps {
    online: boolean;
    children: ReactNode;
}

export default function AppLayout({ online, children }: AppLayoutProps) {
    return (
        <div className="relative min-h-screen overflow-x-hidden bg-slate-950 text-slate-100">
            {/* Floating gradient blobs — purely decorative, sit behind the glass cards. */}
            <div aria-hidden className="pointer-events-none fixed inset-0 overflow-hidden">
                <div className="animate-blob-float absolute -top-24 -left-20 h-80 w-80 rounded-full bg-cyan-500/30 blur-3xl" />
                <div
                    className="animate-blob-float absolute top-1/3 -right-24 h-96 w-96 rounded-full bg-violet-500/25 blur-3xl"
                    style={{ animationDelay: '4s' }}
                />
                <div
                    className="animate-blob-float absolute bottom-0 left-1/4 h-72 w-72 rounded-full bg-emerald-500/20 blur-3xl"
                    style={{ animationDelay: '9s' }}
                />
            </div>

            <div className="relative mx-auto flex min-h-screen max-w-3xl flex-col px-4 pb-16 sm:px-6">
                <header className="sticky top-0 z-10 -mx-4 mb-6 flex items-center justify-between border-b border-white/10 bg-slate-950/70 px-4 py-4 backdrop-blur-lg sm:-mx-6 sm:px-6">
                    <h1 className="bg-gradient-to-r from-cyan-300 via-sky-200 to-violet-300 bg-clip-text text-lg font-bold text-transparent">
                        Skor Saham
                    </h1>
                    <span
                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${
                            online
                                ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300'
                                : 'animate-pulse-ring border-rose-400/30 bg-rose-400/10 text-rose-300'
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
