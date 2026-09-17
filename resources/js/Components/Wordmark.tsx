interface WordmarkProps {
    className?: string;
}

// A blocky "LED dot-matrix" display font (Silkscreen, a genuine pixel typeface — not a CSS
// trick) for guaranteed legibility, plus a small flame accent for a bit of energy.
export default function Wordmark({ className }: WordmarkProps) {
    return (
        <div className={`flex items-center gap-2 ${className ?? ''}`}>
            <svg viewBox="0 0 60 72" className="h-[1.35em] w-auto shrink-0 text-black" aria-hidden>
                <path
                    fill="currentColor"
                    d="M30 2
                       C 40 14 48 24 46 38
                       C 45 46 39 51 31 51
                       C 24 51 18 46 18 38
                       C 18 34 20 31 22 29
                       C 21 36 24 40 29 40
                       C 34 40 37 36 36 30
                       C 35 25 31 22 30 16
                       C 26 22 22 27 22 34
                       C 15 30 11 22 14 12
                       C 16 18 19 21 22 22
                       C 20 14 23 7 30 2 Z"
                />
            </svg>
            <span
                className="text-2xl tracking-wide text-black sm:text-3xl"
                style={{ fontFamily: "'Silkscreen', sans-serif", fontWeight: 700 }}
            >
                JULAK SAHAM
            </span>
        </div>
    );
}
