<?php

namespace Tests\Feature;

use App\Services\ImageTextExtractionService;
use Tests\TestCase;

// Exercises the real tesseract-ocr binary (see Dockerfile) rather than mocking it — OCR quality
// is exactly the thing worth verifying for real here. Skips instead of failing when the binary
// isn't installed on whatever machine runs this suite, since that's an environment concern, not a
// code bug (ManualNewsControllerTest covers the surrounding controller logic with a fake instead,
// so that part of the suite stays portable regardless of whether tesseract is installed).
class ImageTextExtractionServiceTest extends TestCase
{
    private ImageTextExtractionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ImageTextExtractionService::class);

        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('tesseract-ocr is not installed on this machine.');
        }
    }

    private function makeTextImage(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_test_').'.png';
        $im = imagecreatetruecolor(700, 100);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $white);
        imagestring($im, 5, 10, 40, $text, $black);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    public function test_extracts_text_from_a_simple_image(): void
    {
        $path = $this->makeTextImage('Saham BBCA melonjak setelah laba naik');

        $text = $this->service->extractText($path);

        $this->assertStringContainsString('BBCA', $text);
        $this->assertStringContainsString('melonjak', $text);

        unlink($path);
    }

    public function test_throws_for_a_nonexistent_image(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->extractText('/tmp/does-not-exist-'.uniqid().'.png');
    }
}
