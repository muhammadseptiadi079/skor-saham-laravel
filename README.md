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
  Http/Controllers/
    AccuracyController.php    endpoint GET /api/accuracy ("apakah skornya benar-benar akurat?")
  Services/
    StockAnalysisService.php  orkestrasi fetch + scoring untuk satu ticker (dipakai
                               controller & command, supaya tidak duplikat logic)
    AlphaVantageService.php   sumber data pasar global + IPO_CALENDAR
    YahooFinanceService.php   sumber data pasar IDX (harga, volume, fundamental, insider)
    GoogleNewsRssService.php  sumber berita IDX
    SentimentService.php      skor sentimen berbasis kata kunci (IDX)
    SecEdgarService.php       data insider Form 4 dari SEC (global)
    IdxIpoService.php         data IPO IDX, best-effort/belum terverifikasi live
    BacktestService.php       cek skor lama vs harga sekarang, isi kolom evaluasi akurasi
    ScoringEngine.php         logika penggabungan skor & label rekomendasi
  Support/
    TechnicalIndicators.php   RSI, SMA, MACD — dipakai ScoringEngine untuk sub-skor momentum
  Models/
    WatchlistItem.php, AnalysisHistory.php, StockUniverseItem.php, IpoListing.php
  Console/Commands/
    RefreshStockScores.php    php artisan stocks:refresh-scores (isi screener)
    RefreshIpoListings.php    php artisan stocks:refresh-ipo (isi data IPO)
    EvaluateBacktest.php      php artisan stocks:evaluate-backtest (isi akurasi historis)
routes/
  web.php   '/' -> Inertia::render('Dashboard') (satu halaman, semua interaksi client-side)
  api.php   /api/analyze, /api/watchlist, /api/history, /api/screener, /api/ipo, /api/accuracy
  console.php  jadwal harian untuk ketiga command di atas
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

## Fitur peningkatan akurasi

Empat perubahan berikut ditambahkan khusus untuk menjawab "seberapa benar
sih skor ini?" — tetap 100% rule-based/transparan, tidak ada model ML:

- **PEG ratio** — sekarang ikut dinilai di sub-skor fundamental (PEG < 1
  dianggap murah relatif terhadap pertumbuhan labanya sendiri, > 2 dianggap
  mahal). Datanya sebenarnya sudah lama di-fetch dari Alpha Vantage/Yahoo,
  cuma belum pernah dipakai sampai sekarang.
- **Momentum relatif terhadap indeks (IHSG/S&P 500)** — saham naik 5% itu
  biasa saja kalau IHSG lagi naik 8%, tapi kuat kalau IHSG lagi turun.
  `ScoringEngine::scoreMomentum` sekarang membandingkan return 20 hari
  saham vs benchmark-nya (`^JKSE` untuk IDX, `SPY` untuk global — di-cache
  12 jam supaya tidak boros kuota Alpha Vantage karena dipakai bersama oleh
  semua ticker).
- **Sentimen berita ditimbang berdasarkan reputasi sumber** — artikel dari
  outlet besar (Reuters, Bloomberg, Kontan, Bisnis.com, dst — daftar di
  `ScoringEngine::REPUTABLE_SOURCES`) punya bobot 1.3x dibanding sumber
  tidak dikenal, plus kamus kata kunci sentimen Indonesia yang lebih luas
  di `SentimentService`.
- **Info sektor** ditampilkan sebagai konteks di catatan fundamental
  (`Sektor: ...`) — **tidak** dipakai untuk membandingkan rasio (P/E 20
  murah untuk saham tambang, mahal untuk teknologi), karena tidak ada
  sumber data gratis untuk rata-rata rasio per sektor yang bisa diandalkan.
  Kalau nanti ada sumber datanya, ini titik yang paling logis untuk
  diperluas lebih lanjut.
- **Akurasi historis** (`/api/accuracy`, panel "Akurasi Historis" di
  dashboard) — inilah jawaban paling jujur untuk "seberapa akurat":
  `BacktestService` mengecek analisis lama (≥28 hari, ekuivalen ~20 hari
  bursa) lalu melihat harga sekarang — apakah arah yang diprediksi label
  **trading** (Buy/Sell/dst) benar-benar terjadi. Hasilnya diagregasi per
  label dan ditampilkan apa adanya, termasuk kalau hasilnya jelek. Diisi
  lewat:
  ```
  php artisan stocks:evaluate-backtest
  php artisan stocks:evaluate-backtest --min-age-days=28 --limit=50
  ```
  Dijadwalkan otomatis tiap hari jam 05:00. **Perlu waktu untuk terisi** —
  baru ada hasil setelah analisis pertama berumur ≥28 hari, jadi panel ini
  akan kosong di awal pemakaian, itu wajar bukan bug.

Empat perubahan lanjutan (masih 100% rule-based, masih tanpa ML):

- **Badge kelengkapan data** (`X/5 sub-skor tersedia`, di pojok kanan atas
  hasil analisis) — supaya jelas kalau suatu skor dihasilkan dari data
  lengkap atau cuma dari 1-2 sub-skor yang kebetulan tersedia. Hijau =
  lengkap, kuning = separuh, merah = sangat minim.
- **Tren jangka panjang terpisah dari momentum trading** — sebelumnya skor
  **jangka panjang** dan **trading** sama-sama pakai momentum 20 hari yang
  sama, padahal horizonnya beda jauh. Sekarang ada sub-skor baru
  `momentumLongTerm` (`ScoringEngine::scoreLongTermTrend`, pakai window
  sampai 100 hari) yang dipakai khusus untuk skor jangka panjang, sementara
  skor trading tetap pakai momentum 20 hari seperti biasa.
- **Transaksi insider diberi bobot berdasarkan usia** — pembelian minggu
  lalu lebih berarti daripada pembelian 6 bulan lalu. Nilai transaksi kini
  di-diskon: 100% (≤30 hari), 70% (≤90 hari), 40% (≤180 hari), 15% (lebih
  lama). Tanggal yang kosong/tidak valid tidak didiskon (dianggap netral,
  bukan basi).
- **Laporan akurasi arah per sub-skor** (bagian bawah panel "Akurasi
  Historis") — dari data backtest yang ada, seberapa sering arah tiap
  sub-skor (fundamental, berita, momentum, tren panjang, kepemilikan)
  cocok dengan arah harga yang benar-benar terjadi. **Ini cuma laporan
  untuk dibaca manusia** — sengaja tidak otomatis mengubah bobot di
  `ScoringEngine::WEIGHTS`, karena sampel datanya (apalagi di awal
  pemakaian) masih terlalu sedikit untuk kalibrasi otomatis yang aman;
  gampang salah mengira noise sebagai sinyal. Kalau suatu sub-skor
  konsisten performanya jelek selama berbulan-bulan, itu baru alasan kuat
  untuk mempertimbangkan ubah bobotnya secara manual.

Empat perubahan lagi (round ketiga, masih rule-based/tanpa ML):

- **Target harga analis** (`analystTargetPrice`) — sudah lama di-fetch dari
  Yahoo (`financialData.targetMeanPrice`) dan Alpha Vantage
  (`AnalystTargetPrice`) tapi belum pernah dipakai, seperti PEG dulu. Kalau
  target konsensus analis jauh di atas harga sekarang, itu sinyal positif
  tambahan di sub-skor fundamental — datanya "gratis" (sudah ikut di request
  yang sama, tidak ada panggilan API tambahan).
- **Peringatan likuiditas rendah** — kalau rata-rata nilai transaksi harian
  20 hari terakhir di bawah ambang (Rp1 miliar untuk IDX, $1 juta untuk
  global), muncul catatan "Likuiditas rendah" di sub-skor momentum plus
  badge di header hasil analisis. Ini **cuma peringatan, tidak mengubah
  skor** — RSI/MACD/momentum memang kurang bisa diandalkan di saham
  bervolume tipis, jadi user perlu tahu, bukan diam-diam dikoreksi.
- **Peringatan mendekati tanggal laporan keuangan** — kalau laporan
  keuangan berikutnya diperkirakan dalam ≤14 hari, muncul catatan risiko
  (volatilitas bisa naik menjelang rilis). **Cuma untuk saham IDX** —
  datanya "menumpang" di request Yahoo `quoteSummary` yang sudah ada
  (modul `calendarEvents`, tanpa biaya API tambahan). Untuk saham global
  sengaja di-skip: Alpha Vantage butuh panggilan `EARNINGS_CALENDAR`
  terpisah per ticker, dan kuota 25 request/hari sudah sangat ketat —
  menambah 1 panggilan lagi per analisis global tidak sepadan.
- **Akurasi dipecah per kondisi pasar** (bagian "Akurasi per Kondisi
  Pasar" di panel Akurasi Historis) — setiap baris riwayat yang dievaluasi
  kini juga ditandai `bull`/`bear`/`sideways` berdasarkan pergerakan
  benchmark (IHSG/S&P 500) di periode yang sama (`BacktestService`). Supaya
  ketahuan apakah sinyal "Buy" benar-benar bagus, atau cuma kelihatan bagus
  karena kebetulan seluruh pasar lagi naik. Baris riwayat lama (sebelum
  kolom ini ada) otomatis dikecualikan dari breakdown ini, tapi tetap
  masuk hitungan akurasi keseluruhan.

Empat perubahan lagi (round keempat, masih rule-based/tanpa ML):

- **Tren rekomendasi analis** (`analystRatings`) — jumlah Strong Buy/Buy/
  Hold/Sell/Strong Sell bulan berjalan, ikut dinilai di sub-skor
  fundamental (net bullish-bearish dari total analis). Yahoo lewat modul
  `recommendationTrend` (menumpang di request `quoteSummary` yang sama),
  Alpha Vantage lewat field `AnalystRating*` di `OVERVIEW` — keduanya
  tanpa panggilan API tambahan.
- **Dividend yield & rasio payout** — catatan konteks (tidak memengaruhi
  skor, karena yield tinggi bisa berarti kebijakan dividen sehat atau bisa
  juga sekadar harga saham yang lagi jatuh — angka itu sendiri tidak cukup
  untuk menyimpulkan arah). Kalau rasio payout di atas 90%, ditambahkan
  peringatan risiko dividen bisa dipotong.
- **Valuasi relatif sektor** (`SectorValuationService`) — P/E saham
  dibandingkan ke rata-rata P/E (dan PEG) saham lain di sektor yang sama
  **di watchlist milik user sendiri** (pakai fitur pengelompokan sektor
  watchlist), bukan dari sumber eksternal — karena memang tidak ada versi
  gratisnya untuk rata-rata sektor IDX. Cuma muncul kalau ticker yang
  dianalisis ada di watchlist dan sektornya diisi (bukan "Lainnya"), serta
  ada minimal 2 saham lain di sektor yang sama yang sudah pernah
  dianalisis. Sampelnya kecil dan self-selected (sebatas isi watchlist
  sendiri) — catatan konteks, bukan skor.
- **Peringatan pengalihan kepemilikan besar** — kalau satu transaksi
  insider nilainya ≥3% dari kapitalisasi pasar, ditandai sebagai
  "kemungkinan pengalihan kepemilikan besar, cek manual siapa pihaknya".
  Ini **bukan** upaya melacak rekam jejak pembeli/penjual di
  pengambilalihan-pengambilalihan lain — tidak ada sumber data gratis yang
  memungkinkan pencarian riwayat seseorang/entitas lintas ticker (nama
  yang sama pun belum tentu entitas yang sama), jadi aplikasi ini jujur
  berhenti di "ini kejadian besar, pelajari sendiri" alih-alih berpura-pura
  tahu rekam jejaknya.

Satu tambahan lagi (round kelima, masih rule-based/tanpa ML — **ini yang
paling spekulatif dari semua fitur di aplikasi ini**, baca catatan
kejujurannya):

- **Tema nasional/makro** (`NationalThemeService`) — mendeteksi isu yang
  lagi ramai diberitakan (kebakaran hutan/kabut asap, kemarau
  panjang/kekeringan, kenaikan harga komoditas batu bara/nikel/CPO) lewat
  pencarian umum di Google News RSS (bukan pencarian per-ticker seperti
  berita saham biasa), lalu dicocokkan ke sektor watchlist yang
  kemungkinan terdampak (mis. kebakaran hutan/asap → sektor Kesehatan
  & Konsumer/Ritel, karena permintaan masker/obat pernapasan/air purifier
  biasanya naik). Kalau tema itu lagi aktif (≥3 berita ditemukan, di-cache
  6 jam) dan sektor watchlist-nya cocok, muncul catatan tematik di
  sub-skor fundamental. **Cuma untuk saham IDX** yang sudah ada di
  watchlist dengan sektor terisi (pakai fitur sektor watchlist).
  - **Kenapa ini cuma catatan, bukan skor**: pemetaan tema→sektor di sini
    adalah tebakan manual berdasarkan pola umum, bukan hubungan
    sebab-akibat yang terverifikasi — "banyak berita soal X" cuma
    menunjukkan topik X sedang ramai dibicarakan, bukan bukti bahwa
    saham tertentu di sektor itu benar-benar terkena dampaknya (apalagi
    kalau perusahaannya tidak benar-benar berjualan produk yang relevan,
    misal rumah sakit di sektor Kesehatan tidak otomatis diuntungkan
    kabut asap seperti produsen masker). Menjadikannya bagian dari skor
    numerik akan memberi kesan presisi yang tidak didukung datanya —
    jadi tetap murni informasi "cek sendiri", konsisten dengan pendekatan
    catatan konteks lain (sektor, valuasi relatif sektor, dsb).

Lima tambahan lagi (round keenam, masih rule-based/tanpa ML):

- **Beta & posisi 52 minggu** — dua catatan konteks baru di sub-skor
  fundamental, keduanya numpang di request Yahoo/Alpha Vantage yang sudah
  ada (tanpa biaya API tambahan). Beta menjelaskan seberapa liar saham ini
  gerak dibanding pasar (>1.2 "lebih volatile", <0.8 "defensif"); posisi
  52-minggu menunjukkan harga sekarang ada di persentase berapa dari
  rentang tertinggi/terendah setahun terakhir. Tidak memengaruhi skor.
- **Volatilitas historis** — beda dari "Likuiditas rendah" yang sudah ada
  (itu soal volume transaksi tipis), ini soal seberapa liar harga itu
  sendiri bergerak hari ke hari (standar deviasi return harian,
  di-annualized). Dihitung dari data harga yang sudah di-fetch, tanpa
  panggilan API tambahan. Catatan risiko, bukan skor.
- **Peringatan mendekati batas ARA/ARB** (auto reject atas/bawah IDX) —
  kalau pergerakan harga hari ini sudah memakai ≥70% dari batas persentase
  ARA/ARB untuk rentang harga saham tersebut, muncul peringatan. **Baca
  ini baik-baik**: tabel persentase ARA/ARB yang dipakai (`ARA_ARB_BANDS`
  di `ScoringEngine`) adalah tabel yang umum dikutip, tapi IDX sudah lebih
  dari sekali merevisi aturan ini (terakhir sekitar 2023) — anggap sebagai
  "perlu dicek", bukan kebenaran mutlak. Verifikasi ke aturan resmi BEI
  terbaru sebelum benar-benar mengandalkan ini untuk keputusan trading.
  Cuma untuk saham IDX.
- **Akurasi per sektor watchlist** — melengkapi breakdown per kondisi
  pasar yang sudah ada: sekarang bisa dilihat sektor mana sinyal "Buy"-nya
  lebih sering benar, dari saham yang ada di watchlist dan sudah diberi
  sektor.
- **Deteksi lonjakan berita** (`NewsVolumeService`) — kalau jumlah berita
  tentang suatu saham jauh di atas rata-rata riwayat analisisnya sendiri
  (≥2x dari rata-rata, minimal 3 riwayat, minimal 5 berita), muncul
  catatan "lagi jadi sorotan" di sub-skor berita. Sengaja dibandingkan ke
  riwayat saham itu sendiri, bukan ambang angka tetap — jumlah berita
  "normal" beda jauh antara bank besar dan saham kecil. Tidak menunjukkan
  arah (bisa kabar baik atau buruk), cuma penanda "ada sesuatu, cek
  sendiri isinya".

## Fitur baru: Watchlist, Riwayat, Screener, dan IPO

- **Watchlist** (`/api/watchlist`) — simpan ticker favorit di server (bukan
  cuma IndexedDB di HP seperti riwayat lama), supaya screener tahu ticker
  mana yang mau dipantau.
  - **Sektor**: setiap item dikelompokkan per sektor (`App\Support\Sectors`
    — Pertambangan, Keuangan & Perbankan, Kesehatan, Konstruksi &
    Infrastruktur, Konsumer & Ritel, Energi, Teknologi, Properti & Real
    Estate, Industri & Manufaktur, Lainnya). Sektornya dipilih manual oleh
    user saat menambah (bisa diubah lagi kapan saja lewat dropdown di
    panel Watchlist) — bukan diambil otomatis dari field `sector` Yahoo
    Finance/Alpha Vantage, karena itu teks bebas yang sering kosong/tidak
    konsisten untuk ticker IDX.
  - **Favorit**: tombol bintang per item (`PATCH /api/watchlist/{id}` dengan
    `is_favorite`) untuk menandai saham yang benar-benar sudah dibeli,
    supaya beda dari yang cuma dipantau. Filter "Favorit saja" di panel
    Watchlist menyaring ke saham berstatus favorit itu saja — murni alat
    bantu pengelompokan, tidak memengaruhi skor `ScoringEngine`.
  - **Starter pack per sektor** (`POST /api/watchlist/starter-pack`,
    `App\Support\SectorStarterPacks`) — tombol "+ Tambah starter pack" di
    panel Watchlist untuk sekali klik menambahkan ~6-12 saham IDX terkenal
    di sektor pilihan (misal Pertambangan: ADRO, PTBA, ANTM, INCO, MDKA,
    ITMG, HRUM, BUMI, TINS, BYAN, DOID, MBMA). Ini daftar statis dari nama
    ticker publik yang memang tercatat di BEI — bukan dari Stockbit/Ajaib
    atau sumber berbayar manapun (lihat kenapa di bagian "Keterbatasan
    yang jujur" di bawah). Ticker yang sudah ada di watchlist di-skip
    (sektor/status favoritnya yang sudah kamu atur tidak ditimpa).
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

**Kenapa tidak konek ke Stockbit/Ajaib** — keduanya tidak punya API publik
resmi untuk pihak ketiga; yang ada di balik aplikasi mereka adalah endpoint
privat khusus aplikasi mobile mereka sendiri. Menyambungkannya berarti
reverse-engineer endpoint privat itu dan menyimpan/mengirim kredensial akun
broker pengguna di dalam aplikasi ini — melanggar ketentuan layanan mereka
dan berisiko akun ke-flag, jadi ini sengaja tidak dibangun. Solusi yang
dipakai untuk masalah "pilihan saham per sektor sedikit" adalah fitur
starter pack di atas: daftar ticker BEI publik yang dikurasi manual, bukan
data broker mana pun.

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
eksternal): migrasi database, seluruh 108 test PHPUnit, `npm run build`
(Vite + TypeScript type-check bersih), dan server `php artisan serve` —
halaman Inertia ter-render, bundle JS/CSS ter-load, semua endpoint
`/api/*` (termasuk `/api/accuracy`) merespons normal.

Satu hal lagi soal fitur akurasi: `BacktestService` mengambil harga
"sekarang" lewat endpoint Yahoo/Alpha Vantage yang sama seperti di atas —
jadi berlaku keterbatasan yang sama (belum diuji live). Dan karena
sifatnya memang perlu waktu (analisis harus berumur ≥28 hari dulu), panel
akurasinya **tidak akan langsung terisi** meski semua endpoint berfungsi
sempurna — itu bagian dari desainnya, bukan tanda ada yang salah.
