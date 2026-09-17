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

## Input Berita Manual (ketik atau screenshot)

Panel "Input Berita Manual" di dashboard membiarkan user menambahkan berita
yang tidak ke-detect otomatis (misal dilihat di Stockbit, grup WhatsApp, atau
aplikasi lain) — diketik langsung, atau upload screenshot yang teksnya
diekstrak lewat OCR. Ditangani oleh `App\Http\Controllers\ManualNewsController`
(`/api/news/manual`):

- **Ketik teks** — langsung dinilai lewat kamus sentimen yang sama dengan
  berita otomatis (`SentimentService`), termasuk menampilkan kata kunci mana
  yang bikin skornya positif/negatif (transparan, bukan black box).
- **Upload screenshot** — teksnya diekstrak pakai **Tesseract OCR**
  (`thiagoalessio/tesseract_ocr`, self-hosted & gratis — bukan API OCR
  berbayar seperti Google/Azure Vision, jadi tidak butuh API key atau
  signup). Butuh binary `tesseract-ocr` ter-install di server (sudah
  ditambahkan ke `Dockerfile`); kalau tidak ada, endpoint balas error yang
  jelas ("OCR tidak tersedia di server ini") alih-alih crash.
- Item yang disimpan (`manual_news_items`) ikut digabung ke sub-skor berita
  ticker terkait selama **14 hari** ke depan (`ManualNewsService`), lalu
  otomatis berhenti dihitung — supaya berita lama tidak diam-diam terus
  memengaruhi analisis berbulan-bulan kemudian.
- Sudah dicoba end-to-end di sesi pengembangan ini (`tesseract` benar-benar
  ter-install & dites, bukan cuma dibaca dari dokumentasi) — screenshot
  teks "Saham ANTM anjlok setelah rugi..." berhasil diekstrak persis dan
  dinilai sangat negatif. Test OCR (`ImageTextExtractionServiceTest`)
  otomatis skip di mesin yang tidak punya `tesseract-ocr` ter-install,
  supaya suite test tetap portable.

**Tidak perlu pilih ticker dulu** — awalnya panel ini mengharuskan user
mengetik ticker sebelum menempel/upload beritanya, padahal justru itu yang
sering tidak diketahui user saat baru baca beritanya. Sekarang alurnya
dibalik lewat `App\Services\TickerDetectionService` dan endpoint
`POST /api/news/manual/detect`:

1. User cuma ketik/upload berita, klik "Analisis" — tanpa ticker.
2. Server (OCR dulu kalau screenshot) mencocokkan teksnya ke setiap
   ticker/nama perusahaan yang **sudah dikenal aplikasi ini**: watchlist
   user, daftar kurasi `stock_universe_items`, dan `SectorStarterPacks`
   (nama IDX yang sudah dikurasi manual, lihat bagian "Fitur baru" di
   bawah). Cocoknya berupa kode ticker yang muncul sebagai kata utuh
   berhuruf besar ("BBCA naik 2%" cocok, "goto" huruf kecil di kalimat
   biasa tidak) atau nama perusahaan (setelah suffiks badan hukum seperti
   "Tbk"/"Inc" dibuang) muncul sebagai substring di teksnya — pencocokan
   string biasa, bukan ML, konsisten dengan filosofi "setiap langkah bisa
   dijelaskan" di `ScoringEngine`.
3. Tepat satu ticker cocok → langsung tersimpan ke ticker itu, tidak ada
   langkah tambahan.
4. Beberapa ticker cocok (misal beritanya menyebut dua bank sekaligus) →
   muncul pilihan tombol, user tinggal klik yang dimaksud.
5. Tidak ada yang cocok (perusahaan yang belum pernah di-watchlist/dikenal
   aplikasi ini) → fallback ke input ticker manual seperti sebelumnya,
   supaya tetap bisa disimpan.

**Keterbatasan yang jujur**: deteksi ini cuma sebagus daftar nama yang
diketahui — ticker global (AAPL, TSLA, dst.) hampir seluruhnya diandalkan
dari kode tickernya sendiri karena tidak ada daftar nama perusahaan global
yang dikurasi di aplikasi ini (beda dengan IDX yang punya `SectorStarterPacks`
sebagai fallback), jadi berita tentang saham global yang belum pernah
di-watchlist/dianalisis kemungkinan besar tidak ke-detect dan jatuh ke
fallback manual.

## Round Ketujuh: Kepemilikan, Dividen, Level Teknikal, Risk-Adjusted Momentum, dan Portfolio Tracker

Empat catatan analisis baru (masih rule-based, numpang di request yang sudah
ada — tanpa biaya API tambahan) plus satu fitur baru yang lebih besar:

- **Kepemilikan insider & institusi** — persentase saham beredar yang
  dipegang insider vs institusi (modul Yahoo `majorHoldersBreakdown`).
  **Cuma tersedia untuk saham IDX** — Alpha Vantage `OVERVIEW` tidak
  menyediakan data ini untuk saham global, jadi field-nya sengaja `null`
  di sana daripada dipaksakan pakai sumber lain.
- **Tanggal Ex Dividen** — melengkapi fitur dividend yield yang sudah ada;
  kalau tanggal Ex Dividen dalam ≤7 hari, muncul catatan pengingat "beli
  sebelum tanggal ini kalau mau dapat dividen periode ini". Tersedia untuk
  IDX (Yahoo `summaryDetail.exDividendDate`) maupun global (Alpha Vantage
  `ExDividendDate`).
- **Level support/resistance** (`TechnicalIndicators::swingLevels`) —
  titik balik harga (swing high/low) dari data harga historis yang sudah
  di-fetch, dicari titik terdekat di atas (resistance) dan di bawah
  (support) harga sekarang. Heuristik chartist klasik, bukan jaminan harga
  akan memantul di level tersebut.
- **Rasio return-terhadap-risiko** — melengkapi volatilitas historis yang
  sudah ada: momentum 20 hari dan volatilitas historis sama-sama
  disetahunkan lalu dibagi, mirip semangat Sharpe ratio (tanpa risk-free
  rate). Cuma catatan konteks, bukan ikut masuk skor — menskor ulang rasio
  dari dua sinyal yang sudah ikut skor sebelumnya cuma menghitung dua kali.
- **Portfolio tracker** (`/api/portfolio`, panel "Portfolio" di dashboard)
  — beda dari fitur-fitur di atas, ini bukan catatan analisis, tapi
  pelacak untung/rugi sungguhan. Saham yang ditandai favorit (`is_favorite`)
  bisa diisi jumlah lembar (`shares_owned`) dan harga beli rata-rata
  (`avg_buy_price`) lewat `PATCH /api/watchlist/{id}` — begitu keduanya
  terisi, otomatis dihitung untung/rugi belum terealisasi terhadap harga
  cache terakhir.
  - **Kenapa cuma satu avg buy price, bukan ledger transaksi lengkap** —
    tracker beneran akurat untuk multi-transaksi (FIFO/rata-rata
    tertimbang per pembelian) butuh tabel ledger buy/sell terpisah, jauh
    lebih besar scope-nya. Yang dibangun di sini pakai satu angka rata-rata
    yang diisi sendiri oleh user — cukup untuk "kira-kira saya untung
    berapa", tidak untuk pembukuan pajak/akuntansi presisi.
  - **Harga "sekarang" itu dari cache, bukan real-time** — diambil dari
    baris `analysis_history` terbaru untuk ticker itu (hasil
    `stocks:refresh-scores` harian, atau kapan pun terakhir kamu
    menganalisis ticker itu lewat pencarian). Tiap holding menampilkan
    tanggal harga itu diambil — kalau sudah lama, jalankan
    `php artisan stocks:refresh-scores` atau analisis ulang ticker-nya
    supaya lebih update.
  - Ringkasan total (modal, nilai, untung/rugi) **dipisah per mata uang**
    (IDX/IDR vs global/USD) — tidak pernah dijumlah campur supaya tidak
    menyesatkan.

## Round Kedelapan: Kualitas Kesimpulan (Bukan Cuma Tambah Sinyal Baru)

Sub-skor individualnya sudah sangat lengkap dari round-round sebelumnya, jadi
round ini fokus ke kualitas *kesimpulan* dari sinyal yang sudah ada:

- **Skor keyakinan/konsensus** (`longterm.confidence` / `trading.confidence`,
  Tinggi/Sedang/Rendah) — melengkapi label Buy/Sell yang sudah ada. Dua
  saham bisa dapat label "Buy" yang sama, tapi satu karena semua sub-skor
  kompak positif, satu lagi karena satu sub-skor kuat menutupi yang lain
  negatif — sekarang beda situasi itu kelihatan. Dihitung dari seberapa
  banyak sub-skor yang **arahnya jelas** (|skor| ≥ 0.1) sepakat dengan arah
  kesimpulan akhir; sub-skor yang skornya mendekati nol dianggap "netral",
  tidak dipaksa masuk salah satu sisi. **Soal keyakinan = kesepakatan,
  bukan soal benar** — angka ini tidak bilang apa-apa soal apakah
  kesimpulannya akhirnya benar, cuma soal seberapa banyak sinyal yang
  searah.
- **Deteksi divergence RSI vs harga** (`TechnicalIndicators::swingPoints`
  + `rsiSeries`) — upgrade dari RSI yang sekarang (cuma baca level
  overbought/oversold). Bearish divergence: harga bikin puncak baru lebih
  tinggi, tapi RSI di titik itu malah lebih rendah dari puncak sebelumnya
  (momentum naik melemah diam-diam). Bullish divergence: kebalikannya di
  titik terendah. Pola klasik analisis teknikal, ikut masuk skor momentum
  (bukan cuma catatan) karena arahnya cukup jelas.
- **Laporan evaluasi bobot** (`SubScoreAccuracyService::weightSuggestions`,
  bagian "Saran Evaluasi Bobot" di panel Akurasi Historis) — begitu
  sub-skor tertentu punya ≥20 sampel backtest, dan akurasi arahnya
  konsisten rendah (<45%) atau tinggi (>65%), muncul saran tertulis
  ("pertimbangkan turunkan/naikkan bobotnya"). **Ini cuma laporan buat
  dibaca manusia — tidak pernah otomatis mengubah `ScoringEngine::WEIGHTS`.**
  Sampel sekecil ini (dan akan selalu kecil untuk aplikasi personal) terlalu
  berisiko untuk auto-tuning — lebih mudah salah mengira noise sebagai
  sinyal beneran. Keputusan tetap di tangan manusia yang baca laporannya.
- **Perbandingan sektor watchlist diperluas ke margin & ROE** — sebelumnya
  cuma P/E & PEG (soal valuasi/mahal-murah). Sekarang ditambah margin
  laba dan ROE, jadi kelihatan bukan cuma "lebih murah/mahal dari sektor
  sejenis di watchlist kamu" tapi juga "lebih/kurang profitable". Sama
  seperti perbandingan P/E — sampel kecil dari watchlist sendiri, bukan
  data resmi sektor.

## Round Kesembilan: Konflik Antar-Horizon, Kontrarian, OBV, dan Validasi Skor Keyakinan

Round ini juga soal kualitas kesimpulan, ditambah satu indikator teknikal
klasik yang belum ada:

- **Peringatan konflik jangka pendek vs jangka panjang**
  (`horizonAlignment` di respons `/api/analyze`) — label "Buy" jangka
  panjang dan "Sell" trading jangka pendek **sama-sama valid sekaligus**
  (dihitung dari sub-skor yang beda, lihat `ScoringEngine::WEIGHTS`), tapi
  kalau dilihat sendiri-sendiri user bisa salah baca sebagai sinyal yang
  kontradiktif/error. Sekarang begitu kedua horizon menyeberangi ambang
  Buy/Sell (±0.15, sama seperti `labelFor`) ke arah berlawanan, muncul
  catatan eksplisit di panel hasil analisis ("koreksi/rebound sementara,
  bukan perubahan arah besar"). Tidak memengaruhi skor sama sekali — cuma
  bikin konflik yang sudah ada di data jadi kelihatan.
- **Catatan kontrarian saat konsensus analis kelewat seragam**
  (di dalam catatan `analystRatings`, `ScoringEngine::contrarianConsensusNote`)
  — begitu ≥5 analis dan ≥90% dari mereka searah (nyaris semua Buy atau
  nyaris semua Sell), muncul catatan bahwa sebagian investor kontrarian
  menganggap konsensus sekuat itu sebagai peringatan ("semua orang sudah
  tahu" bisa berarti optimisme/pesimismenya sudah kepompong di harga).
  **Ini cuma pengingat psikologi pasar, bukan sinyal terarah** — tidak ada
  cara membuktikan lewat backtest apakah insting kontrarian ini benar untuk
  saham tertentu, jadi sengaja tidak pernah masuk skor.
- **On-Balance Volume / OBV** (`TechnicalIndicators::obv`,
  `ScoringEngine::obvSignal`) — indikator teknikal klasik yang belum ada di
  sub-skor momentum: volume ditambahkan ke running total saat harga naik,
  dikurangi saat harga turun. Kalau arah OBV ~20 hari terakhir **sama**
  dengan arah harga, itu konfirmasi (kenaikan/penurunan didukung tekanan
  beli/jual yang nyata) — masuk skor dengan bobot kecil (±0.3). Kalau
  **berlawanan** (misal harga naik tapi OBV turun), itu peringatan
  divergence volume — arahnya cukup jelas secara teori teknikal klasik,
  jadi masuk skor dengan bobot lebih besar (±0.5) dibanding sekadar
  catatan.
- **Akurasi historis dipecah per level keyakinan** (`byConfidence` di
  `/api/accuracy`, kolom baru `trading_confidence` di `analysis_history`)
  — memvalidasi skor keyakinan dari Round Kedelapan itu sendiri: kalau
  akurasi "Tinggi" ternyata tidak jauh beda dari "Rendah", berarti skor
  keyakinannya belum benar-benar menangkap apa-apa dan `confidenceFor()`
  perlu ditinjau ulang. Ditampilkan sebagai tangga Tinggi → Sedang →
  Rendah di panel Akurasi Historis, bukan urutan alfabet/urutan
  kemunculan data.

## Round Kesepuluh: Skor Keyakinan Divalidasi Jadi Saran Nyata

`byConfidence` (Round Kesembilan) baru menampilkan angka mentahnya — masih
perlu manusia yang menyimpulkan sendiri apakah angkanya kelihatan wajar.
Round ini menutup lingkarannya dengan menambahkan kesimpulan otomatisnya,
persis pola yang sama dengan "Saran Evaluasi Bobot" di Round Kedelapan:

- **Laporan kalibrasi keyakinan** (`SubScoreAccuracyService::confidenceCalibrationReport`,
  field `confidenceCalibration` di `/api/accuracy`) — membandingkan akurasi
  level "Tinggi" vs "Rendah" begitu **keduanya** punya ≥15 sampel graded.
  Kalau gap-nya <10 poin persentase (termasuk kalau "Tinggi" ternyata malah
  *lebih rendah* dari "Rendah" — inversi total), pesannya menyebut angka
  aslinya dan menunjuk langsung ke bagian kode yang perlu ditinjau: ambang
  rasio 0.8/0.5 dan `CONFIDENCE_NEUTRAL_BAND` di `ScoringEngine::confidenceFor()`.
- **Selalu tampil, tidak pernah diam-diam kosong** — percobaan pertama
  fitur ini cuma muncul kalau ada yang perlu dikhawatirkan (meniru pola
  "Saran Evaluasi Bobot"), tapi ternyata itu bikin bingung: aplikasi yang
  baru dipakai (belum ada satu pun sampel backtest) kelihatan seperti
  fiturnya tidak ada sama sekali, padahal cuma belum ada datanya. Sekarang
  `confidenceCalibration` selalu mengembalikan salah satu dari tiga status,
  dan panelnya selalu menampilkan pesannya:
  - `insufficient_data` — belum cukup sampel di salah satu/kedua level,
    pesannya bilang persis berapa sampel yang sudah terkumpul dari berapa
    yang dibutuhkan (kotak abu-abu netral).
  - `ok` — gap-nya sudah jelas, keyakinan "Tinggi" memang meyakinkan
    (kotak hijau).
  - `needs_review` — gap-nya tidak meyakinkan atau malah terbalik, saran
    tinjau ulang `confidenceFor()` (kotak kuning).
- **Sengaja cuma bandingkan "Tinggi" vs "Rendah"**, skip "Sedang" — itu
  ujian paling mendasar dari "apakah skor keyakinan ini membedakan apa pun
  sama sekali", bukan uji kalibrasi yang halus per level. Kalau bahkan dua
  ujung ekstrimnya saja tidak beda, meributkan "Sedang" belum ada gunanya.
- **Tetap laporan untuk dibaca manusia, tidak pernah otomatis mengubah
  `confidenceFor()`** — konsisten dengan `weightSuggestions`: sampel
  sekecil ini (khususnya untuk aplikasi personal yang datanya lambat
  terkumpul) terlalu berisiko untuk auto-tuning ambang batas.

## Round Kesebelas: Estimasi Potensi Pergerakan, Ditaruh di Posisi yang Kelihatan

User minta angka "potensi naik berapa persen" yang gampang dilihat.
Godaan paling gampang adalah bikin satu angka tunggal seakan-akan itu
prediksi harga saham ini — tapi itu melanggar prinsip dasar aplikasi ini
sejak awal (`ScoringEngine` selalu bilang eksplisit: bukan prediksi
yang terjamin akurat, pasar keuangan tidak bisa diprediksi secara
andal). Solusinya bukan bikin angka baru, tapi **mengangkat dua angka
yang sudah dihitung aplikasi ini tapi sebelumnya terkubur di dalam teks
catatan**, ditaruh sebagai kotak besar tepat di bawah gauge skor:

- **Target Analis** (`priceTarget` di respons `/api/analyze`,
  `ScoringEngine::scoreFundamentals`) — sebelumnya cuma muncul sebagai
  kalimat di dalam daftar catatan Fundamental ("Target harga analis
  Rp10.500..."), sekarang juga dikembalikan sebagai angka terstruktur
  (`targetPrice`, `upsidePct`) supaya frontend bisa menampilkannya besar
  dan jelas. **Ini bukan aplikasi ini yang memprediksi** — ini konsensus
  target harga dari analis sungguhan (data eksternal dari Yahoo
  Finance/Alpha Vantage), aplikasi cuma menghitung selisihnya dari harga
  sekarang.
- **Riwayat Label** (dihitung di frontend dari `/api/accuracy`'s
  `byLabel`, tidak butuh endpoint baru) — begitu label trading saham
  yang lagi dianalisis (misal "Buy") sudah punya ≥10 sampel backtest,
  muncul kotak kedua: rata-rata pergerakan historis **semua** saham yang
  pernah dapat label itu, dalam ~20 hari perdagangan. **Sengaja dilabeli
  jelas sebagai riwayat gabungan, bukan prediksi untuk saham yang lagi
  dilihat** — beda dari target analis di atas yang memang spesifik ke
  saham tersebut.
- Kedua kotak cuma muncul kalau datanya benar-benar ada (tidak ada
  angka dipaksakan saat data belum cukup) — konsisten dengan pola
  "diam kalau tidak ada yang bisa dikatakan dengan jujur" yang dipakai
  di seluruh aplikasi ini.

## Round Kedua Belas: Tema Terang

Dashboard aslinya pakai tema gelap ala glassmorphism (kartu transparan
blur + blob warna melayang). Diganti total ke tema terang: latar putih
kebiruan (`slate-50`), kartu solid putih dengan border tipis + shadow
halus (`GlassCard`), dan semua warna teks/badge disesuaikan supaya
kontras terbaca di atas putih — bukan sekadar ganti warna latar saja.

- **Kenapa**: untuk aplikasi finansial, latar terang lebih umum (mirip
  Stockbit/RTI) karena hijau-merah naik-turun lebih kontras dan gampang
  dibaca sekilas dibanding di atas latar gelap.
- **Bukan cuma ganti satu variabel warna** — badge sentimen/skor yang
  sebelumnya pakai teks pastel di atas latar gelap tembus pandang
  (`text-emerald-300` di atas `bg-emerald-400/10`, kontras bagus di
  gelap tapi nyaris tak terbaca di putih) diganti jadi teks solid gelap
  di atas latar tint terang (`text-emerald-700` di atas `bg-emerald-50`).
  Jarum gauge skor dan garis grid di chart SVG (sebelumnya putih/abu
  gelap, didesain untuk latar gelap) juga diganti warnanya biar tetap
  kelihatan di atas kartu putih.
- **Kartu statistik gradient (Watchlist/Potensi Naik/Riwayat/IPO) sengaja
  dibiarkan** — itu blok warna solid dengan teks putih di atasnya, tetap
  kontras bagus di latar apa pun, jadi tidak perlu diubah.

## Round Ketiga Belas: Navigasi Tab (Responsif HP & Laptop)

Dashboard-nya sebelumnya satu halaman panjang berisi 8 panel ditumpuk
ke bawah (hasil analisis, berita manual, portfolio, akurasi, watchlist,
screener, IPO, riwayat) — sekarang dipecah jadi 4 tab, dengan navigasi
yang wujudnya beda menurut lebar layar alih-alih satu tampilan yang
dipaksakan sama di HP dan laptop:

- **Di HP** (lebar layar < `sm` breakpoint Tailwind, ~640px): bottom tab
  bar mengambang di bawah layar, ala aplikasi native — gampang dijangkau
  jempol.
- **Di laptop/tablet lebar**: tab horizontal biasa di bawah header,
  dengan garis bawah biru menandai tab aktif — pola navigasi desktop
  yang lebih wajar dibanding bottom bar yang dipaksakan ke layar lebar.
- **Satu komponen (`TabNav`), dua tampilan** — keduanya di-render
  sekaligus di DOM, tapi cuma satu yang kelihatan di lebar layar
  tertentu (lewat class Tailwind `hidden sm:flex` / `sm:hidden`),
  bukan dua komponen terpisah yang harus disinkronkan manual.
- **Pembagian tab**: *Analisis* (pencarian, hasil, input berita manual,
  riwayat tersimpan di HP), *Watchlist* (watchlist + portfolio),
  *Screener* (screener + IPO), *Akurasi* (panel akurasi historis).
  Riwayat ditaruh di tab Analisis karena klik satu baris riwayat
  langsung menampilkan hasilnya di `ResultPanel` yang ada di tab yang
  sama.
- **Kartu statistik di atas jadi bisa diklik** — klik "Watchlist"
  langsung pindah ke tab Watchlist, dst. Memilih ticker dari panel
  Watchlist/Screener atau riwayat lokal juga otomatis memindahkan ke
  tab Analisis supaya hasilnya langsung kelihatan, tidak peduli dari
  tab mana aksinya dipicu.

## Round Keempat Belas: Kartu Statistik Lebih "Mewah"

4 kartu warna-warni di atas dashboard (Watchlist, Potensi Naik, Riwayat
Tersimpan, IPO Terbaru) di-refresh tampilannya — murni kosmetik, tidak
ada perubahan data/logika:

- **Gradient lebih dalam** — dari nada terang (`500→600`) jadi nada jewel-tone
  yang lebih pekat (`600→800`), kesannya lebih premium dibanding sebelumnya
  yang agak pastel.
- **Shadow berwarna sesuai gradiennya** (`shadow-cyan-900/40` dst.) alih-alih
  shadow abu-abu generik, jadi tiap kartu punya "glow" lembut yang senada.
- **Ring tipis putih transparan** (`ring-1 ring-white/15`) di tepi kartu +
  lapisan sheen diagonal (`from-white/20` ke `to-black/10`) supaya
  permukaannya tidak flat, mirip tekstur kaca/kartu fisik.
- Prop baru `glow` di `StatCard` (opsional, ada default abu-abu netral)
  supaya pemakaian di tempat lain tidak wajib ikut berubah kalau ada.

## Round Kelima Belas: Font Kustom + Perbaikan Wordmark

Font sebelumnya ikut default browser (`ui-sans-serif, system-ui`), yang
terasa generik. Sekarang pakai font kustom dari Google Fonts, dimuat lewat
`<link>` di `resources/views/app.blade.php` (bukan `@import` di CSS, supaya
tidak memblokir render):

- **Inter** — font dasar untuk seluruh body teks (`--font-sans`), dipilih
  karena keterbacaannya bagus untuk UI padat data dan angka `tabular-nums`
  rapi.
- **Plus Jakarta Sans** — font tambahan (`--font-display`, kelas
  `font-display`) khusus untuk elemen yang perlu terasa "branded": wordmark
  header, nama saham di hasil analisis, angka besar di kartu statistik, dan
  label skor di gauge. Dipakai sedikit dan selektif, bukan untuk semua judul,
  supaya tidak berlebihan.
- **Wordmark "Skor Saham" dirapikan** — sebelumnya teks kecil (`text-lg`)
  dengan gradient 3 warna yang bikin tulisannya kelihatan berantakan di
  ukuran kecil. Sekarang ada logomark kotak (huruf "S" di atas gradient
  cyan→ungu) + teks lebih besar (`text-xl`, `font-extrabold`,
  `tracking-tight`), dan gradient dipersempit cuma di kata "Saham" (kata
  "Skor" warna solid) supaya lebih tegas dibaca.
- **`theme-color` meta tag diperbaiki** — masih `#0f172a` (gelap) sisa dari
  tema lama sebelum konversi ke tema terang, sekarang `#f8fafc` supaya warna
  status bar browser di HP tidak kontras aneh dengan tampilan terang.

## Round Keenam Belas: Tema Hitam-Putih (Monokrom)

Semua warna aksen dekoratif (cyan, violet, sky, biru, fuchsia, teal — dipakai
di wordmark, kartu statistik, tombol, tab aktif, ikon panel) diganti jadi
abu-abu/hitam. Ini bukan cuma soal warna: sebelumnya ada 4+ warna berbeda
yang bersaing untuk perhatian (kartu biru, hijau, ungu, oranye sekaligus di
layar yang sama) — versi monokrom ini sengaja dibuat lebih "diam" secara
visual, ala produk premium (kartu hitam matte, wordmark hitam solid) yang
menahan diri dari warna supaya kesannya elegan bukan ramai.

- **Yang diganti ke hitam/abu-abu**: wordmark + logomark header, 4 kartu
  statistik (sekarang seragam gradasi `slate-800→black`, dibedakan cuma
  lewat ikon & label karena warnanya sudah sama), tombol utama (Analisis,
  Simpan, Tambah starter pack, dll), tab aktif di navigasi, ikon judul tiap
  panel, bintang favorit di watchlist, badge "Ada di watchlist", dan blob
  dekoratif di background.
- **Yang SENGAJA tidak diubah**: warna hijau/kuning/merah yang menandakan
  skor atau arah pergerakan (gauge Beli/Jual, bar sub-skor, persen naik/
  turun, label keyakinan Tinggi/Sedang/Rendah, badge peringatan data
  kurang/likuiditas rendah). Warna-warna itu bukan dekorasi — itu satu-
  satunya cara cepat membaca "bagus atau tidak" tanpa baca angka satu per
  satu, jadi kalau ikut dihitamkan, justru bikin aplikasi lebih susah
  dipakai, bukan lebih premium. `StatCard` sekarang punya default
  `gradient`/`glow` monokrom, tapi propnya tetap bisa di-override kalau di
  kemudian hari perlu warna lagi di tempat tertentu.

## Round Ketujuh Belas: Wordmark Tanpa Logomark, Gaya Bolong-Bolong

Logomark kotak "S" di depan judul "Skor Saham" (ditambahkan Round 15)
dihapus lagi, diganti gaya tipografi murni — solid, tebal, besar, huruf
kapital semua, dengan lubang bulat acak yang "menembus" tiap huruf:

- **Huruf besar semua** (`uppercase`), `font-extrabold`, ukuran dinaikkan
  (`text-3xl` di HP, `text-4xl` di layar lebih lebar) supaya terasa tegas
  sebagai judul.
- **Efek lubang bulat acak** — dicoba dulu pakai `-webkit-text-stroke`
  (garis tepi/hollow), tapi itu bukan yang dimaksud; efek yang benar dibuat
  lewat CSS `mask-image` yang menumpuk tekstur SVG berisi belasan lingkaran
  kecil di posisi acak (`public/textures/hole-punch.svg`, di-ulang/tile
  lewat `mask-repeat: repeat`) di atas teks hitam solid. Hasilnya teks tetap
  penuh warna hitam, tapi ada bintik-bintik bulat "bolong" tersebar acak di
  tiap huruf — seperti kertas atau logam yang dilubangi (stensil/perforasi),
  bukan sekadar garis tepi. Kelas utilitasnya `.text-holes` di
  `resources/css/app.css`, dengan fallback teks hitam solid (tanpa lubang)
  untuk browser yang tidak dukung `mask-image`, diatur lewat `@supports`.
- Wordmark sekarang cuma teks `<h1>` polos, tidak ada elemen ikon/kotak lagi
  di depannya.

## Round Kedelapan Belas: Performa — Analisis Tidak Lelet Lagi

Diagnosis: klik "Analisis" itu lambat bukan karena scoring-nya berat, tapi
karena `StockAnalysisService` memanggil 4 sumber data eksternal (Yahoo
Finance fundamentals, Yahoo Finance chart, berita Google News, Yahoo
Finance insider tx — untuk IDX; atau Alpha Vantage overview/berita/harga +
SEC EDGAR — untuk global) **satu per satu secara berurutan**, menunggu
setiap request selesai sebelum mulai yang berikutnya. Untuk saham global
yang punya aktivitas insider, ini bahkan lebih parah: `SecEdgarService`
mengambil sampai 10 dokumen Form 4 **satu per satu** juga — total bisa
sampai 14 request berurutan untuk satu kali analisis.

- **Request paralel, bukan berurutan** — `StockAnalysisService::analyzeIdx()`
  dan `analyzeGlobal()` sekarang memakai `Http::pool()` Laravel untuk
  menembak semua request yang saling independen sekaligus, lalu menunggu
  semuanya selesai bareng (bukan gantian). Waktu tunggunya jadi sama
  dengan request paling lambat di antara semuanya, bukan jumlah semuanya.
- **`SecEdgarService::getInsiderTransactions()`** — pengambilan sampai 10
  dokumen Form 4 juga di-pool, jadi satu batch paralel alih-alih 10 request
  berurutan. Ini kemungkinan penyumbang lelet terbesar untuk saham global
  yang aktivitas insider-nya ramai.
- Supaya `Http::pool()` bisa dipakai tanpa mengubah cara kerja method yang
  sudah ada (dan tanpa merusak test), tiap service (`YahooFinanceService`,
  `GoogleNewsRssService`, `AlphaVantageService`) dipecah jadi bagian
  "bangun URL/query" + "parse response" — method lama (`getFundamentals()`,
  `getChart()`, dst.) tetap ada dan berperilaku sama persis untuk pemanggil
  lain (`BacktestService`, `NationalThemeService`), cuma sekarang manggil
  method parse yang sama yang dipakai jalur pool.
- **Cache singkat (2 menit)** untuk data mentah per ticker+market — kalau
  ticker yang sama dianalisis ulang dalam 2 menit (klik dobel tidak
  sengaja, atau lagi coba-coba testing), tidak perlu ambil ulang ke semua
  sumber eksternal. Ini juga menghemat kuota gratis Alpha Vantage yang
  cuma 25 request/hari. Trade-off: berita manual yang ditambahkan user
  persis dalam jendela 2 menit itu baru kelihatan di analisis berikutnya
  setelah cache-nya kedaluwarsa — dianggap sepadan mengingat jendelanya
  pendek.
- Tidak ada perubahan pada hasil analisis (skor, label, dll.) — ini murni
  soal kecepatan, bukan logika. Semua 200 test yang ada tetap lulus tanpa
  perubahan (Http::fake() di test tetap mencegat request meskipun sekarang
  dikirim lewat pool).

## Round Kesembilan Belas: Font Lebih Tegas + Hitam yang Benar-Benar Hitam

Permintaannya: huruf lebih tegas/jenis huruf beda, dan warna hitamnya
dibenahi.

- **Font `--font-display` diganti dari Plus Jakarta Sans ke Archivo** —
  bentuk hurufnya lebih geometris/kokoh dan beratnya sampai 900 (Black),
  dipakai di wordmark, judul nama saham di hasil analisis, dan angka besar
  di kartu statistik. Plus Jakarta Sans maksimal cuma berat 800 dan
  bentuknya lebih membulat/kalem — Archivo terasa lebih "tegas" sesuai
  yang diminta.
- **Wordmark naik dari `font-extrabold` (800) ke `font-black` (900)** —
  berat maksimal yang tersedia, biar makin tebal.
- **Warna "hitam" dibenahi jadi benar-benar netral** — sebelumnya beberapa
  elemen "hitam" (wordmark, kartu statistik, tombol Analisis, tab aktif,
  badge "Ada di watchlist", bintang favorit) sebenarnya pakai
  `slate-800`/`slate-900` yang punya sedikit semburat biru (nilai hex-nya
  `#0f172a`, bukan abu-abu/hitam netral) — kentara di area besar seperti
  kartu statistik. Diganti ke `neutral-800`/`black`/`#0a0a0a` yang benar-
  benar netral, tidak condong ke warna apa pun.
- Judul panel (`Panel.tsx`) dinaikkan dari `font-semibold` ke `font-bold`
  supaya konsisten lebih tegas, tapi tetap pakai Inter (bukan Archivo) dan
  warna `slate-900` (bukan hitam penuh) — supaya hierarki visualnya tetap
  jelas: cuma elemen paling penting (wordmark, nama saham, aksi utama)
  yang dapat hitam solid, bukan semua teks gelap sekaligus.

## Round Kedua Puluh: Ganti Gauge Jarum Jadi Bar Horizontal

Indikator skor "Jangka Panjang"/"Trading" sebelumnya berbentuk speedometer
dengan jarum (`ScoreGauge`) — dicek dulu apakah jarumnya salah hitung
(dites untuk beberapa nilai skor, semuanya jatuh di zona warna yang benar,
jadi bukan bug), tapi bentuk speedometer-nya sendiri dianggap terlihat
jadul untuk tema monokrom yang tegas sekarang.

- **`ScoreGauge` (jarum) dihapus, diganti `ScoreBar`** — track horizontal
  bergradasi warna (merah tua → merah → kuning → hijau → hijau tua, sama
  persis dengan zona lama) dengan penanda hitam di posisi skornya, bukan
  jarum berputar.
- Dipilih bar horizontal (bukan cincin/ring melingkar) karena bentuknya
  konsisten dengan `SubScoreBarChart` yang sudah ada di bagian bawah hasil
  analisis — jadi cuma satu gaya chart di seluruh halaman, bukan dua gaya
  berbeda yang bersaing.
- Preview-nya dicek dulu lewat screenshot sebelum dipasang permanen.

## Round Kedua Puluh Satu: Transisi Halus Saat Pindah Tab

Sebelumnya pindah tab (Analisis/Watchlist/Screener/Akurasi) langsung
"loncat" tanpa animasi. Sekarang tiap kali pindah tab, konten tab yang
baru muncul dengan animasi `fadeInUp` (fade + geser naik sedikit) yang
sama dengan animasi yang sudah dipakai di kartu hasil analisis — dipilih
supaya konsisten dengan motion yang sudah ada, bukan menambah gaya
animasi baru. Tidak butuh library animasi tambahan (masih CSS murni),
karena tiap tab memang sudah di-mount ulang setiap kali `activeTab`
berubah (bukan cuma disembunyikan), jadi animasi "masuk"-nya otomatis
terpicu ulang tiap pindah tab.

## Round Kedua Puluh Dua: Ikon Aplikasi, Empty State, dan Indikator Loading

Tiga kesenjangan visual yang ditemukan lewat audit singkat, dikerjakan
sekaligus:

- **Ikon aplikasi (PWA/home-screen) diganti** — sebelumnya kotak polos
  warna teal-hijau (`public/icons/icon-192.png`/`icon-512.png`), sisa dari
  awal proyek, sama sekali tidak nyambung dengan brand hitam-putih
  sekarang. Diganti kotak hitam solid dengan huruf "S" putih tebal —
  senada dengan wordmark. `manifest.json`-nya juga dibetulkan sekalian:
  `background_color`/`theme_color` masih `#0f172a` (gelap, sisa tema
  lama sebelum konversi ke tema terang), sekarang `#f8fafc` biar splash
  screen PWA-nya tidak gelap tiba-tiba.
- **Empty state dipercantik** — komponen baru `EmptyState` (ikon bulat +
  pesan) menggantikan teks abu-abu polos di kondisi "belum ada data":
  Watchlist, Riwayat, Screener, IPO, Portfolio, dan Akurasi Historis.
  Sengaja cuma dipakai di empty-state utama tiap panel (bukan di setiap
  pesan "tidak ada catatan" yang lebih kecil/kontekstual), supaya jadi
  penanda visual yang berarti, bukan dekorasi yang diulang di mana-mana.
- **Indikator loading saat menganalisis** — tombol "Analisis" dan banner
  status di bawahnya sekarang menampilkan ikon spinner berputar
  (`SpinnerIcon` baru + `animate-spin` dari Tailwind) selagi menunggu
  hasil, bukan cuma teks "Menganalisis..." tanpa elemen visual apa pun.

## Round Kedua Puluh Tiga: Grafik Tren & Favicon Ikut Dibenahi

Audit visual lanjutan menemukan dua sisa yang kelewatan dari konversi ke
tema monokrom:

- **`HistoryLineChart` (grafik tren skor trading) masih biru langit**
  (`#38bdf8`) — satu-satunya elemen di seluruh aplikasi yang masih pakai
  warna dari tema warna-warni lama, "nyasar" di tengah tampilan yang
  sekarang serba hitam-putih. Garis dan area gradasinya diganti hitam
  (`#0a0a0a`); titik-titik datanya tetap merah/kuning/hijau sesuai skor
  (itu bukan dekorasi, sama seperti prinsip yang sudah dipakai di
  `ScoreBar`/`SubScoreBarChart`).
- **`public/favicon.ico` ternyata file kosong (0 byte)** sisa dari awal
  proyek — dibuatkan ulang (multi-resolusi 16/32/48px) dari ikon PWA yang
  baru, jadi kalau ada browser yang minta `/favicon.ico` langsung
  (sebagian browser tetap melakukan ini meski sudah ada `<link
  rel="icon">`), tidak dapat file kosong/rusak.

## Round Kedua Puluh Empat: Empat Fitur untuk Derajat Kepercayaan Analisis

Bukan soal visual — empat penambahan yang murni soal transparansi: bikin
analisis lebih jujur soal apa yang tidak diketahuinya, tanpa menambah
satu pun klaim/prediksi baru.

- **Akurasi historis tiap sub-skor langsung di hasil analisis** —
  sebelumnya data "Fundamental akurat 62% dari 30 sampel" dsb. cuma ada
  di tab Akurasi terpisah (`SubScoreAccuracyService::report()`). Sekarang
  muncul langsung sebagai catatan kecil di bawah tiap bar sub-skor di
  `SubScoreBarChart`, jadi kelihatan tanpa pindah tab. Digerbang sampel
  minimal 10 (sama seperti `MIN_SAMPLE_FOR_HISTORICAL_NOTE` yang sudah
  dipakai untuk catatan "Riwayat Label").
- **Peringatan kalau sub-skor saling bertentangan**
  (`ScoringEngine::subScoreDivergenceNote()`) — sebelumnya cuma dicek
  "trading vs jangka panjang" (`horizonAlignment`), belum dicek
  pertentangan ANTAR sub-skor dalam horizon yang sama. Sekarang kalau
  ada sub-skor yang jelas positif (≥0.15) berbarengan dengan yang jelas
  negatif (≤-0.15) — misalnya Fundamental bagus tapi Momentum jelek —
  muncul catatan eksplisit menyebut sub-skor mana yang bertentangan,
  supaya sinyal campur-aduk yang tersembunyi di balik satu angka
  gabungan tidak disembunyikan dari user.
- **Peringatan data harga basi** (`ScoringEngine::priceFreshnessNote()`)
  — kalau data harga terakhir yang dipakai untuk momentum/tren ternyata
  sudah ≥5 hari (API sumber data lag, bursa libur panjang, dll.),
  sekarang ada peringatan eksplisit alih-alih diam-diam menghitung skor
  dari harga basi. Ambang 5 hari sengaja longgar supaya akhir pekan +
  satu hari libur normal tidak salah kena flag.
- **Catatan kondisi pasar saat ini** (`ScoringEngine::currentMarketRegime()`)
  — dihitung dari pergerakan indeks acuan (IHSG/S&amp;P 500) ~20 hari
  terakhir, memakai ambang +/-3% yang sama dengan yang sudah dipakai
  `BacktestService` untuk menandai rezim pasar tiap baris backtest.
  Kalau kondisi pasar sekarang cukup mirip salah satu rezim yang sudah
  cukup banyak sampelnya di `/api/accuracy`, muncul catatan "skor
  trading aplikasi ini akurat X% dari N sampel dalam kondisi pasar
  seperti ini" — bukan prediksi baru, cuma konteks jujur soal kapan
  metode ini secara historis lebih/kurang bisa diandalkan.
- Semua empat fitur ini nullable dan gagal aman (`null` kalau datanya
  tidak cukup) — tidak ada yang memaksakan tampil kalau memang belum
  ada dasarnya, konsisten dengan filosofi "jangan pura-pura tahu" yang
  sudah dipakai di `confidenceCalibrationReport()`.

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
eksternal): migrasi database, seluruh 200 test PHPUnit, `npm run build`
(Vite + TypeScript type-check bersih), dan server `php artisan serve` —
halaman Inertia ter-render, bundle JS/CSS ter-load, semua endpoint
`/api/*` (termasuk `/api/accuracy`) merespons normal.

Satu hal lagi soal fitur akurasi: `BacktestService` mengambil harga
"sekarang" lewat endpoint Yahoo/Alpha Vantage yang sama seperti di atas —
jadi berlaku keterbatasan yang sama (belum diuji live). Dan karena
sifatnya memang perlu waktu (analisis harus berumur ≥28 hari dulu), panel
akurasinya **tidak akan langsung terisi** meski semua endpoint berfungsi
sempurna — itu bagian dari desainnya, bukan tanda ada yang salah.
