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

// The bottom bar's indicator is one continuous stroke spanning all tabs (not a separate bar per
// tab), cresting into a "gelombang" (wave) right above whichever tab is active and flat
// elsewhere — built as a single path so its shape (same command structure every time, only the
// crest's x-position changes) can morph smoothly via the CSS `d` property instead of jumping.
const WAVE_VIEW_WIDTH = 100;
const WAVE_VIEW_HEIGHT = 22;
const WAVE_BASELINE_Y = 17;
const WAVE_CREST_Y = 3;
const WAVE_SHOULDER_SPREAD = 12; // how far the crest's slope reaches out before flattening

function waveIndicatorPath(activeIndex: number, tabCount: number): string {
    const crestX = ((activeIndex + 0.5) / tabCount) * WAVE_VIEW_WIDTH;
    const leftShoulder = Math.max(0, crestX - WAVE_SHOULDER_SPREAD);
    const rightShoulder = Math.min(WAVE_VIEW_WIDTH, crestX + WAVE_SHOULDER_SPREAD);
    const leftControl = crestX - WAVE_SHOULDER_SPREAD / 2;
    const rightControl = crestX + WAVE_SHOULDER_SPREAD / 2;

    return (
        `M 0,${WAVE_BASELINE_Y} ` +
        `L ${leftShoulder},${WAVE_BASELINE_Y} ` +
        `C ${leftControl},${WAVE_BASELINE_Y} ${leftControl},${WAVE_CREST_Y} ${crestX},${WAVE_CREST_Y} ` +
        `C ${rightControl},${WAVE_CREST_Y} ${rightControl},${WAVE_BASELINE_Y} ${rightShoulder},${WAVE_BASELINE_Y} ` +
        `L ${WAVE_VIEW_WIDTH},${WAVE_BASELINE_Y}`
    );
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
                    {/* One continuous line across all tabs that crests into a wave right above the
                        active tab and flattens elsewhere — morphs smoothly to the new tab's
                        position via the CSS `d` property (path() notation) rather than jumping. */}
                    <svg
                        aria-hidden
                        viewBox={`0 0 ${WAVE_VIEW_WIDTH} ${WAVE_VIEW_HEIGHT}`}
                        preserveAspectRatio="none"
                        className="pointer-events-none absolute inset-x-0 top-0 h-5 w-full"
                    >
                        <path
                            fill="none"
                            stroke="#000"
                            strokeWidth={2.5}
                            strokeLinecap="round"
                            style={{
                                d: `path("${waveIndicatorPath(activeIndex, tabs.length)}")`,
                                transition: 'd 400ms cubic-bezier(0.34, 1.56, 0.64, 1)',
                            }}
                        />
                    </svg>
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
