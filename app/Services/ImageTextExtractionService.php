<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

// OCR for the "upload a screenshot" news input — wraps the tesseract-ocr system binary via
// thiagoalessio/tesseract_ocr (a free, self-hosted OCR engine; no external API key or paid
// service). Requires the `tesseract-ocr` package to be installed on the server (see Dockerfile) —
// isAvailable() lets callers fail with a clear message instead of a crash when it's missing.
class ImageTextExtractionService
{
    public function isAvailable(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        return (bool) trim((string) @shell_exec('which tesseract 2>/dev/null'));
    }

    // Indonesian + English, since financial headlines in screenshots are often a mix of both
    // (bahasa Indonesia body text, English tickers/company names).
    public function extractText(string $imagePath): string
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('OCR (tesseract) tidak tersedia di server ini — hubungi admin, atau ketik manual saja.');
        }

        try {
            $text = (new TesseractOCR($imagePath))->lang('ind', 'eng')->run();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gagal membaca teks dari gambar: '.$e->getMessage());
        }

        return trim($text);
    }
}
