# Skor Saham (Laravel)

Versi Laravel dari aplikasi PWA analisis skor saham — fungsinya identik dengan
versi Node.js: skor jangka panjang & trading dari berita, laporan keuangan,
tren volume, dan transaksi beli/jual insider/pemilik. Mendukung saham **IDX
(Indonesia)** dan **global**.

Frontend (PWA: HTML, CSS, JS, manifest, service worker) **sama persis**
dengan versi Node — hanya backend-nya yang di-porting ke Laravel, supaya
formatnya lebih familiar buat yang sudah biasa pakai Laravel.

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

## Struktur proyek

```
app/
  Http/Controllers/AnalyzeController.php   endpoint GET /api/analyze
  Services/
    AlphaVantageService.php    sumber data pasar global
    YahooFinanceService.php    sumber data pasar IDX (harga, volume, fundamental, insider)
    GoogleNewsRssService.php   sumber berita IDX
    SentimentService.php       skor sentimen berbasis kata kunci (IDX)
    SecEdgarService.php        data insider Form 4 dari SEC (global)
    ScoringEngine.php          logika penggabungan skor & label rekomendasi
routes/
  api.php   GET /api/analyze?ticker=&market=
  web.php   GET /  -> menyajikan PWA (public/pwa-shell.html)
public/
  pwa-shell.html, css/, js/, manifest.json, service-worker.js, icons/
```

## Menjalankan di lokal

```
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
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
