import { type ReactNode, useLayoutEffect, useRef, useState } from 'react';

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

// Mobile bottom nav is a floating black capsule whose top edge stays flat except right above the
// active tab, where it rises into a smooth hill that pokes above the capsule — the active icon
// sits up inside that hill while its label stays anchored at the normal row. Built from a single
// closed SVG path (filled black, stroked white) so the hill's position can morph via the CSS `d`
// property instead of jumping; every corner/side command stays byte-identical between renders,
// only the hill's own coordinates change, which is what lets the browser interpolate smoothly.
const BAR_TOP_Y = 24; // y of the flat top edge (px) — headroom above it is where the hill rises into
const BAR_BOTTOM_Y = 76; // y of the flat bottom edge (px), i.e. the capsule's total height
const CREST_Y = 8; // y of the hill's peak for the active tab (px)
const CORNER_RADIUS = (BAR_BOTTOM_Y - BAR_TOP_Y) / 2; // fully rounded capsule ends
const SHOULDER_SPREAD = 48; // how far the hill's slope reaches out before flattening (px)
const ICON_LIFT_PX = 11; // how far the active tab's icon rises up into the hill

function capsulePath(width: number, activeIndex: number, tabCount: number): string {
    const r = CORNER_RADIUS;
    const crestX = ((activeIndex + 0.5) / tabCount) * width;
    const leftShoulder = Math.max(r, crestX - SHOULDER_SPREAD);
    const rightShoulder = Math.min(width - r, crestX + SHOULDER_SPREAD);
    const leftControl = crestX - SHOULDER_SPREAD / 2;
    const rightControl = crestX + SHOULDER_SPREAD / 2;

    return (
        `M ${r},${BAR_TOP_Y} ` +
        `L ${leftShoulder},${BAR_TOP_Y} ` +
        `C ${leftControl},${BAR_TOP_Y} ${leftControl},${CREST_Y} ${crestX},${CREST_Y} ` +
        `C ${rightControl},${CREST_Y} ${rightControl},${BAR_TOP_Y} ${rightShoulder},${BAR_TOP_Y} ` +
        `L ${width - r},${BAR_TOP_Y} ` +
        `A ${r},${r} 0 0 1 ${width},${BAR_TOP_Y + r} ` +
        `L ${width},${BAR_BOTTOM_Y - r} ` +
        `A ${r},${r} 0 0 1 ${width - r},${BAR_BOTTOM_Y} ` +
        `L ${r},${BAR_BOTTOM_Y} ` +
        `A ${r},${r} 0 0 1 0,${BAR_BOTTOM_Y - r} ` +
        `L 0,${BAR_TOP_Y + r} ` +
        `A ${r},${r} 0 0 1 ${r},${BAR_TOP_Y} ` +
        `Z`
    );
}

// Rendered twice — a horizontal row under the header for wide screens, a fixed bottom bar for
// phones — same tabs/state, just different chrome per screen size instead of one layout
// awkwardly stretched to fit both. Only one of the two is ever visible at a given width.
export default function TabNav({ tabs, active, onChange }: TabNavProps) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(360);

    useLayoutEffect(() => {
        const el = wrapRef.current;
        if (!el) return;
        const measure = () => setWidth(el.clientWidth);
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    const activeIndex = Math.max(
        0,
        tabs.findIndex((t) => t.id === active)
    );

    return (
        <>
            {/* Hidden again at lg: and up — the sidebar layout there shows every panel at once,
                so switching "tabs" would have nothing left to do. */}
            <nav className="mb-6 hidden gap-1 border-b border-slate-200 sm:flex lg:hidden">
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
                className="fixed inset-x-0 bottom-0 z-20 flex justify-center px-3 sm:hidden"
                style={{ paddingBottom: 'calc(env(safe-area-inset-bottom) + 10px)' }}
            >
                <div ref={wrapRef} className="relative w-full" style={{ height: BAR_BOTTOM_Y }}>
                    <svg
                        aria-hidden
                        width={width}
                        height={BAR_BOTTOM_Y}
                        className="pointer-events-none absolute inset-0 drop-shadow-[0_6px_16px_rgba(0,0,0,0.28)]"
                    >
                        <path
                            fill="#000"
                            stroke="#fff"
                            strokeOpacity={0.85}
                            strokeWidth={2}
                            style={{
                                d: `path("${capsulePath(width, activeIndex, tabs.length)}")`,
                                transition: 'd 400ms cubic-bezier(0.34, 1.56, 0.64, 1)',
                            }}
                        />
                    </svg>
                    <div className="absolute inset-x-0 bottom-0 flex" style={{ height: BAR_BOTTOM_Y - BAR_TOP_Y }}>
                        {tabs.map((tab) => {
                            const isActive = tab.id === active;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => onChange(tab.id)}
                                    className="flex flex-1 flex-col items-center justify-center gap-0.5 text-[11px]"
                                >
                                    <span
                                        className={isActive ? 'text-white' : 'text-white/55'}
                                        style={{
                                            display: 'inline-flex',
                                            transform: isActive ? `translateY(-${ICON_LIFT_PX}px)` : undefined,
                                            transition: 'transform 400ms cubic-bezier(0.34, 1.56, 0.64, 1)',
                                        }}
                                    >
                                        {tab.icon}
                                    </span>
                                    <span className={isActive ? 'font-bold text-white' : 'font-medium text-white/55'}>
                                        {tab.label}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            </nav>
        </>
    );
}
