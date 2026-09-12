<?php

namespace App\Support;

// A small curated list of well-known IDX-listed tickers per sector, for the watchlist's "starter
// pack" button. The actual gap users hit isn't Yahoo Finance lacking mining-sector data — it can
// analyze any ticker fine — it's not knowing which tickers exist in a sector they haven't already
// followed (the curated LQ45 sample used by the screener only has a handful of mining names).
// These are plain public facts (BEI-listed company/ticker names), not sourced from any
// brokerage's private data — see the "kenapa bukan Stockbit/Ajaib" note in the README.
class SectorStarterPacks
{
    private const PACKS = [
        'Pertambangan' => [
            ['ticker' => 'ADRO', 'name' => 'Alamtri Resources Indonesia Tbk'],
            ['ticker' => 'PTBA', 'name' => 'Bukit Asam Tbk'],
            ['ticker' => 'ANTM', 'name' => 'Aneka Tambang Tbk'],
            ['ticker' => 'INCO', 'name' => 'Vale Indonesia Tbk'],
            ['ticker' => 'MDKA', 'name' => 'Merdeka Copper Gold Tbk'],
            ['ticker' => 'ITMG', 'name' => 'Indo Tambangraya Megah Tbk'],
            ['ticker' => 'HRUM', 'name' => 'Harum Energy Tbk'],
            ['ticker' => 'BUMI', 'name' => 'Bumi Resources Tbk'],
            ['ticker' => 'TINS', 'name' => 'Timah Tbk'],
            ['ticker' => 'BYAN', 'name' => 'Bayan Resources Tbk'],
            ['ticker' => 'DOID', 'name' => 'Delta Dunia Makmur Tbk'],
            ['ticker' => 'MBMA', 'name' => 'Merdeka Battery Materials Tbk'],
        ],
        'Keuangan & Perbankan' => [
            ['ticker' => 'BBCA', 'name' => 'Bank Central Asia Tbk'],
            ['ticker' => 'BBRI', 'name' => 'Bank Rakyat Indonesia Tbk'],
            ['ticker' => 'BMRI', 'name' => 'Bank Mandiri Tbk'],
            ['ticker' => 'BBNI', 'name' => 'Bank Negara Indonesia Tbk'],
            ['ticker' => 'BRIS', 'name' => 'Bank Syariah Indonesia Tbk'],
            ['ticker' => 'BBTN', 'name' => 'Bank Tabungan Negara Tbk'],
            ['ticker' => 'ARTO', 'name' => 'Bank Jago Tbk'],
            ['ticker' => 'BNGA', 'name' => 'Bank CIMB Niaga Tbk'],
            ['ticker' => 'PNBN', 'name' => 'Bank Pan Indonesia Tbk'],
            ['ticker' => 'NISP', 'name' => 'Bank OCBC NISP Tbk'],
        ],
        'Kesehatan' => [
            ['ticker' => 'KLBF', 'name' => 'Kalbe Farma Tbk'],
            ['ticker' => 'KAEF', 'name' => 'Kimia Farma Tbk'],
            ['ticker' => 'SIDO', 'name' => 'Industri Jamu dan Farmasi Sido Muncul Tbk'],
            ['ticker' => 'SILO', 'name' => 'Siloam International Hospitals Tbk'],
            ['ticker' => 'MIKA', 'name' => 'Mitra Keluarga Karyasehat Tbk'],
            ['ticker' => 'HEAL', 'name' => 'Medikaloka Hermina Tbk'],
            ['ticker' => 'TSPC', 'name' => 'Tempo Scan Pacific Tbk'],
            ['ticker' => 'PRDA', 'name' => 'Prodia Widyahusada Tbk'],
        ],
        'Konstruksi & Infrastruktur' => [
            ['ticker' => 'WIKA', 'name' => 'Wijaya Karya Tbk'],
            ['ticker' => 'WSKT', 'name' => 'Waskita Karya Tbk'],
            ['ticker' => 'PTPP', 'name' => 'PP (Persero) Tbk'],
            ['ticker' => 'ADHI', 'name' => 'Adhi Karya Tbk'],
            ['ticker' => 'JSMR', 'name' => 'Jasa Marga Tbk'],
            ['ticker' => 'SMGR', 'name' => 'Semen Indonesia Tbk'],
            ['ticker' => 'INTP', 'name' => 'Indocement Tunggal Prakarsa Tbk'],
            ['ticker' => 'TOTL', 'name' => 'Total Bangun Persada Tbk'],
        ],
        'Konsumer & Ritel' => [
            ['ticker' => 'UNVR', 'name' => 'Unilever Indonesia Tbk'],
            ['ticker' => 'ICBP', 'name' => 'Indofood CBP Sukses Makmur Tbk'],
            ['ticker' => 'INDF', 'name' => 'Indofood Sukses Makmur Tbk'],
            ['ticker' => 'MYOR', 'name' => 'Mayora Indah Tbk'],
            ['ticker' => 'AMRT', 'name' => 'Sumber Alfaria Trijaya Tbk'],
            ['ticker' => 'MAPI', 'name' => 'Mitra Adiperkasa Tbk'],
            ['ticker' => 'ACES', 'name' => 'Aspirasi Hidup Indonesia Tbk'],
            ['ticker' => 'CPIN', 'name' => 'Charoen Pokphand Indonesia Tbk'],
        ],
        'Energi' => [
            ['ticker' => 'PGAS', 'name' => 'Perusahaan Gas Negara Tbk'],
            ['ticker' => 'MEDC', 'name' => 'Medco Energi Internasional Tbk'],
            ['ticker' => 'ELSA', 'name' => 'Elnusa Tbk'],
            ['ticker' => 'AKRA', 'name' => 'AKR Corporindo Tbk'],
            ['ticker' => 'RAJA', 'name' => 'Rukun Raharja Tbk'],
            ['ticker' => 'ENRG', 'name' => 'Energi Mega Persada Tbk'],
        ],
        'Teknologi' => [
            ['ticker' => 'GOTO', 'name' => 'GoTo Gojek Tokopedia Tbk'],
            ['ticker' => 'BUKA', 'name' => 'Bukalapak.com Tbk'],
            ['ticker' => 'EMTK', 'name' => 'Elang Mahkota Teknologi Tbk'],
            ['ticker' => 'MTDL', 'name' => 'Metrodata Electronics Tbk'],
            ['ticker' => 'WIFI', 'name' => 'Solusi Sinergi Digital Tbk'],
        ],
        'Properti & Real Estate' => [
            ['ticker' => 'BSDE', 'name' => 'Bumi Serpong Damai Tbk'],
            ['ticker' => 'CTRA', 'name' => 'Ciputra Development Tbk'],
            ['ticker' => 'PWON', 'name' => 'Pakuwon Jati Tbk'],
            ['ticker' => 'SMRA', 'name' => 'Summarecon Agung Tbk'],
            ['ticker' => 'APLN', 'name' => 'Agung Podomoro Land Tbk'],
            ['ticker' => 'DMAS', 'name' => 'Puradelta Lestari Tbk'],
        ],
        'Industri & Manufaktur' => [
            ['ticker' => 'ASII', 'name' => 'Astra International Tbk'],
            ['ticker' => 'UNTR', 'name' => 'United Tractors Tbk'],
            ['ticker' => 'AUTO', 'name' => 'Astra Otoparts Tbk'],
            ['ticker' => 'SMSM', 'name' => 'Selamat Sempurna Tbk'],
            ['ticker' => 'ARNA', 'name' => 'Arwana Citramulia Tbk'],
            ['ticker' => 'IMPC', 'name' => 'Impack Pratama Industri Tbk'],
        ],
    ];

    /** @return string[] */
    public static function sectors(): array
    {
        return array_keys(self::PACKS);
    }

    /** @return array<int, array{ticker: string, name: string}> */
    public static function forSector(string $sector): array
    {
        return self::PACKS[$sector] ?? [];
    }
}
