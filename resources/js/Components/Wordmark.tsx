interface WordmarkProps {
    className?: string;
}

// The exact logo image the user provided — used as-is, not recreated with CSS/fonts.
export default function Wordmark({ className }: WordmarkProps) {
    return <img src="/images/wordmark.jpg" alt="Julak Saham" className={`h-9 w-auto sm:h-12 ${className ?? ''}`} />;
}
