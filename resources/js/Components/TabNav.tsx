import type { ReactNode } from 'react';

export interface TabDef {
    id: string;
    label: string;
    icon: ReactNode;
}

interface TabNavProps {
    tabs: TabDef[];
    active: string;
    onChange: (id: string) => void;
}

// Rendered twice — a horizontal row under the header for wide screens, a fixed bottom bar for
// phones — same tabs/state, just different chrome per screen size instead of one layout
// awkwardly stretched to fit both. Only one of the two is ever visible at a given width.
export default function TabNav({ tabs, active, onChange }: TabNavProps) {
    const activeIndex = Math.max(
        0,
        tabs.findIndex((t) => t.id === active)
    );

    return (
        <>
            <nav className="mb-6 hidden gap-1 border-b border-slate-200 sm:flex">
                {tabs.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => onChange(tab.id)}
                        className={`-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm transition-colors ${
                            active === tab.id
                                ? 'border-black font-bold text-black'
                                : 'border-transparent font-medium text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        {tab.icon}
                        {tab.label}
                    </button>
                ))}
            </nav>

            <nav
                className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur-lg sm:hidden"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                <div className="relative flex">
                    {/* Full-width-per-tab indicator that slides to the active tab with a springy
                        overshoot (same easing as animate-pop-in) instead of just recoloring text —
                        the "gelombang" (wave) motion the bottom bar was missing. */}
                    <span
                        aria-hidden
                        className="absolute top-0 h-[3px] rounded-full bg-black transition-transform duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
                        style={{ width: `${100 / tabs.length}%`, transform: `translateX(${activeIndex * 100}%)` }}
                    />
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => onChange(tab.id)}
                            className={`flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] transition-colors ${
                                active === tab.id ? 'font-bold text-black' : 'font-medium text-slate-500'
                            }`}
                        >
                            {tab.icon}
                            {tab.label}
                        </button>
                    ))}
                </div>
            </nav>
        </>
    );
}
