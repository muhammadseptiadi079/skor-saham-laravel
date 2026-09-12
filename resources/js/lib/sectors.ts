// Mirrors App\Support\Sectors::ALL on the backend — a user-assigned taxonomy for grouping the
// watchlist, not derived from Yahoo/Alpha Vantage's inconsistent sector field.
export const DEFAULT_SECTOR = 'Lainnya';

export const SECTORS = [
    'Pertambangan',
    'Keuangan & Perbankan',
    'Kesehatan',
    'Konstruksi & Infrastruktur',
    'Konsumer & Ritel',
    'Energi',
    'Teknologi',
    'Properti & Real Estate',
    'Industri & Manufaktur',
    DEFAULT_SECTOR,
] as const;
