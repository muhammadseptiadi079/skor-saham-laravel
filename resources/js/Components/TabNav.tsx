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
    return (
        <>
            <nav className="mb-6 hidden gap-1 border-b border-slate-200 sm:flex">
                {tabs.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => onChange(tab.id)}
                        className={`-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                            active === tab.id
                                ? 'border-slate-900 text-slate-900'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        {tab.icon}
                        {tab.label}
                    </button>
                ))}
            </nav>

            <nav
                className="fixed inset-x-0 bottom-0 z-20 flex border-t border-slate-200 bg-white/95 backdrop-blur-lg sm:hidden"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                {tabs.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => onChange(tab.id)}
                        className={`flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium transition-colors ${
                            active === tab.id ? 'text-slate-900' : 'text-slate-500'
                        }`}
                    >
                        {tab.icon}
                        {tab.label}
                    </button>
                ))}
            </nav>
        </>
    );
}
