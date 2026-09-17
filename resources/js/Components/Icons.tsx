// Hand-written line icons (no icon library) — kept to one consistent stroke style (24x24,
// round joins, currentColor) so they can be recolored/animated purely through Tailwind classes.
import type { SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement>;

const base = {
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.75,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
};

export function SearchIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <circle cx="10.5" cy="10.5" r="6.5" />
            <path d="M20 20l-4.7-4.7" />
        </svg>
    );
}

export function StarIcon({ filled, ...props }: IconProps & { filled?: boolean }) {
    return (
        <svg {...base} fill={filled ? 'currentColor' : 'none'} {...props}>
            <path d="M12 3.5l2.6 5.4 5.9.7-4.3 4.1 1.1 5.9L12 16.8l-5.3 2.8 1.1-5.9-4.3-4.1 5.9-.7L12 3.5z" />
        </svg>
    );
}

export function ChartUpIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M3 17l6-6 4 4 8-9" />
            <path d="M15 6h6v6" />
        </svg>
    );
}

export function ChartDownIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M3 7l6 6 4-4 8 9" />
            <path d="M15 18h6v-6" />
        </svg>
    );
}

export function BriefcaseIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <rect x="3" y="8" width="18" height="12" rx="2" />
            <path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            <path d="M3 13h18" />
        </svg>
    );
}

export function CoinsIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <ellipse cx="9" cy="7" rx="6" ry="3.2" />
            <path d="M3 7v5c0 1.77 2.69 3.2 6 3.2s6-1.43 6-3.2V7" />
            <path d="M3 12v5c0 1.77 2.69 3.2 6 3.2 2.06 0 3.88-.53 5-1.36" />
            <ellipse cx="17" cy="14.5" rx="4" ry="2.2" />
            <path d="M13 14.5v3c0 1.2 1.79 2.2 4 2.2s4-1 4-2.2v-3" />
        </svg>
    );
}

export function ClockIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
        </svg>
    );
}

export function BellIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M6 10a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10z" />
            <path d="M10 19a2 2 0 0 0 4 0" />
        </svg>
    );
}

export function UsersIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <circle cx="9" cy="8" r="3.2" />
            <path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" />
            <circle cx="17.5" cy="9" r="2.6" />
            <path d="M15.5 13.6c2.6.4 4.5 2.4 4.5 5.4" />
        </svg>
    );
}

export function CloseIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M6 6l12 12M18 6L6 18" />
        </svg>
    );
}

export function WifiIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M2.5 9.5a14 14 0 0 1 19 0" />
            <path d="M5.8 13a9.5 9.5 0 0 1 12.4 0" />
            <path d="M9 16.5a5 5 0 0 1 6 0" />
            <circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none" />
        </svg>
    );
}

export function WifiOffIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M2.5 9.5c1.6-1.3 3.4-2.2 5.3-2.7M12 6.5c2.7 0 5.3.9 7.5 2.5" />
            <path d="M5.8 13a9.5 9.5 0 0 1 4.2-2.1M14.2 11.3a9.5 9.5 0 0 1 3.9 1.7" />
            <path d="M9 16.5a5 5 0 0 1 6 0" />
            <circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none" />
            <path d="M3 3l18 18" />
        </svg>
    );
}

export function NewsIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <rect x="3.5" y="5" width="14" height="14" rx="1.5" />
            <path d="M7.5 9h6M7.5 12.5h6M7.5 16h3.5" />
            <path d="M17.5 8.5h1a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2h-1" />
        </svg>
    );
}

export function GaugeIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <path d="M4 15a8 8 0 1 1 16 0" />
            <path d="M12 15l4.5-4.5" />
            <circle cx="12" cy="15" r="1.2" fill="currentColor" stroke="none" />
        </svg>
    );
}

// Meant to be spun via Tailwind's `animate-spin` on the caller — kept as a plain (non-animating)
// icon here so it composes with any element's own transition classes.
export function SpinnerIcon(props: IconProps) {
    return (
        <svg {...base} {...props}>
            <circle cx="12" cy="12" r="9" opacity={0.25} />
            <path d="M21 12a9 9 0 0 0-9-9" />
        </svg>
    );
}
