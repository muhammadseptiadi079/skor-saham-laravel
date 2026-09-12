<?php

namespace App\Http\Controllers;

use App\Models\ManualNewsItem;
use App\Services\ImageTextExtractionService;
use App\Services\ManualNewsService;
use App\Services\SentimentService;
use Illuminate\Http\Request;

class ManualNewsController extends Controller
{
    public function __construct(
        private ManualNewsService $manualNews,
        private ImageTextExtractionService $imageText,
        private SentimentService $sentiment,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'ticker' => ['required', 'string', 'max:20'],
            'market' => ['required', 'in:idx,global'],
        ]);

        $items = $this->manualNews->forTicker($validated['ticker'], $validated['market']);

        return response()->json($items->map(fn (ManualNewsItem $item) => $this->present($item)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticker' => ['required', 'string', 'max:20'],
            'market' => ['required', 'in:idx,global'],
            'text' => ['required_without:image', 'nullable', 'string', 'max:2000'],
            'image' => ['required_without:text', 'nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            try {
                $text = $this->imageText->extractText($request->file('image')->getRealPath());
            } catch (\RuntimeException $e) {
                return response()->json(['error' => 'ocr_failed', 'message' => $e->getMessage()], 422);
            }

            if ($text === '') {
                return response()->json([
                    'error' => 'ocr_empty',
                    'message' => 'Tidak ada teks yang terbaca dari gambar ini — coba screenshot yang lebih jelas, atau ketik manual.',
                ], 422);
            }

            $source = 'screenshot';
        } else {
            $text = trim($validated['text']);
            $source = 'typed';
        }

        $item = $this->manualNews->store($validated['ticker'], $validated['market'], $text, $source);

        return response()->json($this->present($item), 201);
    }

    public function destroy(ManualNewsItem $manualNewsItem)
    {
        $manualNewsItem->delete();

        return response()->json(['deleted' => true]);
    }

    private function present(ManualNewsItem $item): array
    {
        return [
            'id' => $item->id,
            'ticker' => $item->ticker,
            'market' => $item->market,
            'text' => $item->text,
            'source' => $item->source,
            'sentimentScore' => $item->sentiment_score,
            'matchedKeywords' => $this->sentiment->matchedKeywords($item->text),
            'createdAt' => $item->created_at->toIso8601String(),
        ];
    }
}
