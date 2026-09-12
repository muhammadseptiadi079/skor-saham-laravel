<?php

namespace Tests\Unit;

use App\Services\SentimentService;
use PHPUnit\Framework\TestCase;

class SentimentServiceTest extends TestCase
{
    private SentimentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SentimentService;
    }

    public function test_matched_keywords_finds_positive_words(): void
    {
        $result = $this->service->matchedKeywords('Laba perusahaan naik dan kinerja solid');

        $this->assertContains('laba', $result['positive']);
        $this->assertContains('naik', $result['positive']);
        $this->assertContains('kinerja solid', $result['positive']);
        $this->assertSame([], $result['negative']);
    }

    public function test_matched_keywords_finds_negative_words(): void
    {
        $result = $this->service->matchedKeywords('Perusahaan merugi dan sahamnya anjlok');

        $this->assertContains('rugi', $result['negative']);
        $this->assertContains('anjlok', $result['negative']);
        $this->assertSame([], $result['positive']);
    }

    public function test_matched_keywords_returns_empty_for_neutral_text(): void
    {
        $result = $this->service->matchedKeywords('Perusahaan mengadakan rapat tahunan');

        $this->assertSame([], $result['positive']);
        $this->assertSame([], $result['negative']);
    }
}
