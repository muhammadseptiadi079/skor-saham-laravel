interface WordmarkProps {
    className?: string;
}

// Bold italic text (always fully legible — never bent along a curve, which distorted the
// letters into an unreadable blob in an earlier attempt) plus a small flame accent and a wavy
// swoosh underline for a bit of the "flowing" energy from the reference logo.
export default function Wordmark({ className }: WordmarkProps) {
    return (
        <div className={`flex flex-col ${className ?? ''}`}>
            <div className="flex items-center gap-2">
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
                <span className="font-display text-3xl font-black tracking-wide text-black italic sm:text-4xl">
                    JULAK SAHAM
                </span>
            </div>
            <svg viewBox="0 0 300 10" preserveAspectRatio="none" className="mt-1 h-[6px] w-full text-black" aria-hidden>
                <path
                    d="M 2,3 C 60,-3 110,9 170,4 C 220,0 260,8 298,2"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={4.5}
                    strokeLinecap="round"
                />
            </svg>
        </div>
    );
}
