interface WordmarkProps {
    className?: string;
}

interface LedPanelProps {
    fontSize: number;
    dotSize: number;
    padding: string;
    className?: string;
}

// A genuine LED dot-matrix sign look: a dark panel with a full grid of dim "unlit" dots behind
// the text, and the text itself quantized into the same dot grid via a repeating radial-gradient
// CSS mask (not a real font — Silkscreen's smooth vector glyphs get punched into dots here, same
// grid pitch on both layers so the lit dots land exactly on cells). The dot grid needs real size
// to stay legible, so two fixed-size variants are rendered and toggled by breakpoint rather than
// scaling one panel down — a small panel with tiny dots just turns into an illegible smudge.
function LedPanel({ fontSize, dotSize, padding, className }: LedPanelProps) {
    const maskImage = `radial-gradient(circle, #000 36%, transparent 40%)`;
    const dotBackground = `radial-gradient(rgba(255,255,255,0.16) 36%, transparent 40%)`;

    return (
        <div className={`relative overflow-hidden rounded-lg bg-[#0a0a0a] ${className ?? ''}`} style={{ padding }}>
            <div
                aria-hidden
                className="pointer-events-none absolute"
                style={{ inset: `-${dotSize}px`, backgroundImage: dotBackground, backgroundSize: `${dotSize}px ${dotSize}px` }}
            />
            <span
                className="relative block text-white"
                style={{
                    fontFamily: "'Silkscreen', sans-serif",
                    fontWeight: 700,
                    fontSize,
                    lineHeight: 1,
                    letterSpacing: fontSize > 30 ? 1.5 : 1,
                    whiteSpace: 'nowrap',
                    filter: 'drop-shadow(0 0 2px rgba(255,255,255,0.65))',
                    WebkitMaskImage: maskImage,
                    maskImage,
                    WebkitMaskSize: `${dotSize}px ${dotSize}px`,
                    maskSize: `${dotSize}px ${dotSize}px`,
                    WebkitMaskRepeat: 'repeat',
                    maskRepeat: 'repeat',
                }}
            >
                JULAK SAHAM
            </span>
        </div>
    );
}

export default function Wordmark({ className }: WordmarkProps) {
    return (
        <span role="img" aria-label="Julak Saham" className={className}>
            <LedPanel fontSize={34} dotSize={5.5} padding="14px 16px" className="sm:hidden" />
            <LedPanel fontSize={46} dotSize={7.5} padding="18px 22px" className="hidden sm:inline-block" />
        </span>
    );
}
