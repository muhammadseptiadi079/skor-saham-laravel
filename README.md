# Skor Saham (Laravel)

Versi Laravel dari aplikasi PWA analisis skor saham — fungsinya identik dengan
versi Node.js: skor jangka panjang & trading dari berita, laporan keuangan,
tren volume, dan transaksi beli/jual insider/pemilik. Mendukung saham **IDX
(Indonesia)** dan **global**.

Frontend-nya sudah di-rewrite total dari PWA vanilla JS/CSS ke **React +
TypeScript via Inertia.js**, dengan tampilan glassmorphism, gradient stat
card, ikon & chart SVG custom, dan animasi CSS — lihat bagian "Stack &
tampilan" di bawah. PWA (offline support, installable ke home screen) tetap
dipertahankan.

## Yang harus dipahami dulu

**Ini bukan alat prediksi yang akurat.** Skornya berbasis aturan sederhana
dan transparan (rinciannya tampil di layar hasil) — bahan pertimbangan
tambahan, bukan satu-satunya dasar keputusan.

- **Data IDX** dari endpoint Yahoo Finance yang tidak resmi — bisa berhenti
  berfungsi sewaktu-waktu.
- **Sentimen berita IDX** dari pencocokan kata kunci sederhana, bukan NLP
  sungguhan.
- **Data global** pakai Alpha Vantage gratis (25 request/hari per key).
- **Insider/pemilik untuk saham global** dari SEC EDGAR (Form 4) — resmi,
  gratis, tanpa API key.
- **Insider untuk saham IDX** kemungkinan besar "tidak tersedia" — memang
  keterbatasan data, bukan bug.
- **Data IPO IDX** dari endpoint publik idx.co.id yang tidak resmi/belum
  terverifikasi live (lihat bagian "Keterbatasan yang jujur" di bawah) —
  bisa saja kosong atau perlu penyesuaian.

## Struktur proyek

```
app/
  Http/Controllers/
    AnalyzeController.php     endpoint GET /api/analyze
    WatchlistController.php   endpoint /api/watchlist (list/add/remove)
    HistoryController.php     endpoint GET /api/history
    ScreenerController.php    endpoint GET /api/screener ("potensi naik")
    IpoController.php         endpoint GET /api/ipo
  Services/
    StockAnalysisService.php  orkestrasi fetch + scoring untuk satu ticker (dipakai
                               controller & command, supaya tidak duplikat logic)
    AlphaVantageService.php   sumber data pasar global + IPO_CALENDAR
    YahooFinanceService.php   sumber data pasar IDX (harga, volume, fundamental, insider)
    GoogleNewsRssService.php  sumber berita IDX
    SentimentService.php      skor sentimen berbasis kata kunci (IDX)
    SecEdgarService.php       data insider Form 4 dari SEC (global)
    IdxIpoService.php         data IPO IDX, best-effort/belum terverifikasi live
    ScoringEngine.php         logika penggabungan skor & label rekomendasi
  Support/
    TechnicalIndicators.php   RSI, SMA, MACD — dipakai ScoringEngine untuk sub-skor momentum
  Models/
    WatchlistItem.php, AnalysisHistory.php, StockUniverseItem.php, IpoListing.php
  Console/Commands/
    RefreshStockScores.php    php artisan stocks:refresh-scores (isi screener)
    RefreshIpoListings.php    php artisan stocks:refresh-ipo (isi data IPO)
routes/
  web.php   '/' -> Inertia::render('Dashboard') (satu halaman, semua interaksi client-side)
  api.php   /api/analyze, /api/watchlist, /api/history, /api/screener, /api/ipo
  console.php  jadwal harian untuk kedua command di atas
resources/
  views/app.blade.php       root view Inertia (@vite + @inertia)
  js/
    app.tsx                 bootstrap Inertia + registrasi service worker
    Pages/Dashboard.tsx      satu-satunya halaman: search, stat card, semua panel
    Components/
      AppLayout.tsx          background blob glassmorphism + topbar online/offline
      GlassCard.tsx          panel kaca transparan yang dipakai di mana-mana
      Icons.tsx              ikon SVG custom (bukan library)
      charts/                ScoreGauge, SubScoreBarChart, HistoryLineChart (SVG manual)
      dashboard/             StatCard, SearchForm, ResultPanel, Watchlist/Screener/Ipo/HistoryPanel
    lib/api.ts, lib/db.ts    fetch wrapper ke /api/*, wrapper IndexedDB (riwayat offline)
    types.ts                 tipe TypeScript untuk semua payload API
  css/app.css                Tailwind v4 + keyframe animasi custom
public/
  manifest.json, service-worker.js, icons/   (shell PWA lama sudah dihapus, diganti build Vite)
```

## Stack & tampilan

- **Backend**: Laravel 13, SQLite, PHPUnit.
- **Frontend**: React + TypeScript, dijembatani ke Laravel lewat
  **Inertia.js** (jadi terasa seperti SPA tanpa reload penuh, tapi routing
  tetap di Laravel) — bukan lagi HTML/JS vanilla. Build via **Vite**.
  Styling **Tailwind CSS v4** (utility class, config CSS-first lewat
  `@theme`, tanpa `tailwind.config.js`).
- **Desain**: glassmorphism (panel blur transparan + blob warna mengambang
  di background, lihat `AppLayout.tsx`), gradient stat card di bagian atas
  dashboard, ikon SVG custom (`Components/Icons.tsx`, bukan dari library
  ikon pihak ketiga).
- **Chart**: semua grafik (gauge skor, bar chart rincian sub-skor, line
  chart tren riwayat) dibuat manual pakai SVG polos di
  `Components/charts/` — bukan Chart.js/Recharts.
- **Animasi**: keyframe CSS custom di `resources/css/app.css` —
  `fade-in-up`, `pop-in`, `row-in`, `slide-in-left` untuk elemen/baris
  masuk, `page-in` untuk transisi halaman, `pulse-ring` untuk sinyal
  "perlu perhatian" (skor Strong Sell), `breathe` untuk ikon watermark di
  stat card, plus micro-interaction hover (`scale`) pakai Tailwind biasa.

## Menjalankan di lokal

```
cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed        # isi daftar LQ45/blue-chip untuk screener
npm run build              # atau: npm run dev (hot reload saat development)
php artisan serve
```

Isi `ALPHA_VANTAGE_API_KEY` di `.env` dengan key gratis dari
https://www.alphavantage.co/support/#api-key. `SEC_USER_AGENT` boleh
dibiarkan default.

Buka `http://localhost:8000` — install ke layar utama HP lewat menu
share/browser seperti biasa.

## Deploy gratis (Render, via Docker)

Render tidak punya runtime PHP native, jadi proyek ini disertai
`Dockerfile` supaya tetap bisa jalan di Render sebagai "Web Service" tipe
Docker:

1. Push proyek ini ke GitHub (lewat Working Copy di iPhone atau `git push`
   biasa).
2. Di Render: **New +** → **Web Service** → connect ke repo ini.
3. Render otomatis mendeteksi `Dockerfile` — biarkan **Environment: Docker**.
4. Tambahkan Environment Variables di dashboard Render:
   - `ALPHA_VANTAGE_API_KEY` = key kamu
   - `SEC_USER_AGENT` = bebas, misal `SkorSahamPWA/1.0 (personal use)`
   - `APP_ENV` = `production`
   - `APP_DEBUG` = `false`
5. Deploy → Render kasih URL publik, buka di Safari → **Add to Home
   Screen**.

Catatan: `Dockerfile` menjalankan `php artisan serve` di dalam container.
Ini cukup untuk pemakaian personal skala kecil; untuk trafik besar biasanya
dipakai Nginx + PHP-FPM, tapi untuk kebutuhan ini `artisan serve` sudah
memadai dan jauh lebih sederhana di-setup.

## Cara kerja skor

Sama seperti versi Node — lihat penjelasan lengkap di `readme.md` proyek
Node (`stock-predictor-pwa`): 4 sub-skor (fundamental, berita, momentum &
volume, kepemilikan & insider) digabung dengan bobot berbeda untuk horizon
jangka panjang vs trading.

Sub-skor **momentum & volume** sekarang juga memasukkan indikator teknikal
standar (semua tetap rule-based & transparan, dihitung di
`app/Support/TechnicalIndicators.php`):

- **RSI(14)** — jenuh beli (≥70) dianggap sinyal waspada koreksi, jenuh jual
  (≤30) dianggap potensi rebound.
- **SMA20 vs SMA50** — "golden cross" (SMA20 di atas SMA50) dibaca bullish,
  "death cross" sebaliknya. Butuh minimal 50 hari data harga.
- **MACD(12,26,9)** — histogram positif (MACD di atas garis sinyal) dibaca
  bullish. Butuh minimal 35 hari data harga.

Indikator yang datanya belum cukup otomatis dilewati (tidak memaksakan nilai
kosong), jadi sub-skor momentum tetap jalan walau baru punya 20 hari data.

## Fitur baru: Watchlist, Riwayat, Screener, dan IPO

- **Watchlist** (`/api/watchlist`) — simpan ticker favorit di server (bukan
  cuma IndexedDB di HP seperti riwayat lama), supaya screener tahu ticker
  mana yang mau dipantau.
- **Riwayat** (`/api/history`) — setiap kali `/api/analyze` dipanggil,
  hasilnya otomatis tersimpan ke tabel `analysis_history`. Gagal simpan
  tidak akan menggagalkan response analisa (best-effort).
- **Screener "Potensi Naik"** (`/api/screener?market=idx|global`) —
  menampilkan ticker dengan skor trading/longterm terakhir di atas ambang
  "Buy" (≥0.15), diambil dari **cache** di `analysis_history`, bukan
  dihitung langsung saat request (supaya tidak boros kuota Alpha Vantage).
  Cache-nya diisi oleh:
  ```
  php artisan stocks:refresh-scores          # scan watchlist + daftar kurasi
  php artisan stocks:refresh-scores --budget=10   # batasi request Alpha Vantage per run
  ```
  Dijadwalkan otomatis tiap hari jam 03:00 lewat `routes/console.php` (perlu
  cron `* * * * * php artisan schedule:run` di server, atau `php artisan
  schedule:work` saat development).
- **Daftar kurasi** (`stock_universe_items`, diisi oleh
  `StockUniverseSeeder`) — contoh saham LQ45 (IDX) dan blue-chip (global)
  yang ikut di-screening selain watchlist milik user. LQ45 di-review IDX
  tiap ~6 bulan, jadi daftar ini bisa saja tidak 100% akurat — sesuaikan
  lewat tabel tersebut kalau perlu.
- **IPO** (`/api/ipo?market=idx|global`) — IPO global dari Alpha Vantage
  (`IPO_CALENDAR`, endpoint resmi & gratis), IPO IDX best-effort (lihat di
  bawah). Diisi lewat:
  ```
  php artisan stocks:refresh-ipo
  ```
  Dijadwalkan otomatis tiap hari jam 04:00.

## Keterbatasan yang jujur (baca sebelum lapor "kok kosong?")

Sesi pengembangan ini berjalan di sandbox yang **memblokir semua akses
jaringan keluar** (termasuk ke Yahoo Finance, Alpha Vantage, SEC EDGAR,
Google News, dan idx.co.id) — jadi tidak ada satu pun panggilan HTTP nyata
yang bisa diuji langsung selama menulis kode ini. Semua pengujian otomatis
(`php artisan test`) memakai `Http::fake()` untuk mem-mock response setiap
provider berdasarkan skema yang sudah didokumentasikan (Alpha Vantage, SEC)
atau yang teramati sebelumnya (Yahoo, Google News RSS) — ini memverifikasi
logic parsing & scoring, **bukan** bahwa endpoint live-nya masih persis
sama hari ini.

Yang paling perlu di-double-check begitu jalan di lingkungan dengan akses
internet normal:

- **`app/Services/IdxIpoService.php`** — endpoint yang dipakai
  (`https://www.idx.co.id/primary/ListedCompany/GetIPO`) mengikuti pola
  yang biasa dipakai situs IDX untuk widget listed-company mereka, tapi
  **belum pernah berhasil di-hit dari sandbox ini**, jadi path atau nama
  field JSON-nya bisa saja sudah berbeda. Kalau `php artisan
  stocks:refresh-ipo` menghasilkan 0 entri IDX, cek response asli endpoint
  tersebut (misal lewat `php artisan tinker` →
  `Http::get(...)->body()`, atau lihat tab Network di browser saat buka
  halaman IPO di idx.co.id) lalu sesuaikan `parseRows()` di file itu. Kode
  ini didesain supaya gagal dengan aman (list kosong), tidak pernah bikin
  aplikasi crash.
- Endpoint Yahoo Finance, Alpha Vantage, dan SEC EDGAR yang sudah ada dari
  sebelumnya juga tidak sempat diuji ulang secara live pada sesi ini —
  kemungkinan besar masih jalan seperti biasa (tidak ada perubahan pada
  cara memanggilnya, hanya `range` chart Yahoo yang diperpanjang dari 3
  bulan ke 6 bulan agar cukup data untuk SMA50/MACD), tapi tetap sepadan
  untuk di-smoke-test sekali di lokal.
- **`Dockerfile`** — sudah diubah jadi multi-stage (stage Node untuk build
  Vite, stage PHP untuk runtime) supaya deploy tetap jalan dengan frontend
  baru. `docker build` **belum sempat dicoba** di sesi ini (docker daemon
  tidak tersedia di sandbox) — sebelum deploy ke Render, jalankan `docker
  build .` sekali secara lokal untuk memastikan tidak ada typo/step yang
  meleset.

Yang **sudah** diverifikasi jalan di sesi ini (tanpa perlu akses internet
eksternal): migrasi database, seluruh 24 test PHPUnit, `npm run build`
(Vite + TypeScript type-check bersih), dan server `php artisan serve` —
halaman Inertia ter-render, bundle JS/CSS ter-load, semua endpoint
`/api/*` merespons normal.
