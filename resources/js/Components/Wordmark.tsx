interface WordmarkProps {
    className?: string;
}

// A flowing black ribbon (single thick-stroked path, round caps) with a flame flick on the left
// and a curling tail on the right — the app name rides the same curve via <textPath> so the
// letters follow the ribbon's wave instead of sitting on a straight baseline.
export default function Wordmark({ className }: WordmarkProps) {
    return (
        <svg
            viewBox="0 0 640 170"
            className={className}
            role="img"
            aria-label="Julak Saham"
        >
            <defs>
                <path id="wordmark-ribbon" d="M 95,118 C 150,55 215,55 275,78 C 335,101 385,128 445,108 C 505,88 525,38 595,52" />
            </defs>

            {/* flame flick, left end */}
            <path
                fill="currentColor"
                d="M 95,118
                   C 60,118 45,100 30,80
                   C 45,88 62,90 78,84
                   C 60,78 48,64 42,46
                   C 62,58 82,64 100,60
                   C 92,80 92,102 95,118
                   Z"
            />

            {/* ribbon body */}
            <use href="#wordmark-ribbon" fill="none" stroke="currentColor" strokeWidth={64} strokeLinecap="round" />

            {/* curling tail, right end */}
            <path
                fill="currentColor"
                d="M 592,50
                   C 618,52 636,68 636,94
                   C 636,114 622,128 604,126
                   C 617,118 624,102 620,86
                   C 617,72 606,60 592,50
                   Z"
            />

            <text
                fill="#fff"
                fontFamily="var(--font-display)"
                fontWeight={900}
                fontSize={52}
                letterSpacing={0.5}
            >
                <textPath href="#wordmark-ribbon" startOffset="5.5%">
                    JULAK SAHAM
                </textPath>
            </text>
        </svg>
    );
}
