<?php

namespace App\Http\Controllers;

use App\Models\ManualNewsItem;
use App\Services\ImageTextExtractionService;
use App\Services\ManualNewsService;
use App\Services\SentimentService;
use App\Services\TickerDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualNewsController extends Controller
{
    public function __construct(
        private ManualNewsService $manualNews,
        private ImageTextExtractionService $imageText,
        private SentimentService $sentiment,
        private TickerDetectionService $tickerDetection,
    ) {}

    // Lets the frontend resolve which stock(s) a piece of news is about *before* asking the user
    // to type a ticker — the whole point of this feature (see TickerDetectionService). Returns the
    // OCR'd/typed text alongside the candidates so the frontend never needs to re-run OCR: once the
    // user (or the auto-pick, when there's exactly one match) settles on a ticker, it calls store()
    // with this same text.
    public function detect(Request $request)
    {
        $validated = $request->validate([
            'text' => ['required_without:image', 'nullable', 'string', 'max:2000'],
            'image' => ['required_without:text', 'nullable', 'image', 'max:5120'],
        ]);

        [$text, $errorResponse] = $this->resolveText($request, $validated);
        if ($errorResponse) {
            return $errorResponse;
        }

        return response()->json([
            'text' => $text,
            'candidates' => $this->tickerDetection->detect($text),
        ]);
    }

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

        [$text, $errorResponse] = $this->resolveText($request, $validated);
        if ($errorResponse) {
            return $errorResponse;
        }

        $source = $request->hasFile('image') ? 'screenshot' : 'typed';
        $item = $this->manualNews->store($validated['ticker'], $validated['market'], $text, $source);

        return response()->json($this->present($item), 201);
    }

    public function destroy(ManualNewsItem $manualNewsItem)
    {
        $manualNewsItem->delete();

        return response()->json(['deleted' => true]);
    }

    // Shared by detect() and store(): OCR the screenshot (if one was sent) or just trim the typed
    // text. Returns [text, null] on success, or [null, JsonResponse] with the OCR failure/empty
    // response the caller should return as-is.
    /** @return array{0: string|null, 1: JsonResponse|null} */
    private function resolveText(Request $request, array $validated): array
    {
        if (! $request->hasFile('image')) {
            return [trim($validated['text']), null];
        }

        try {
            $text = $this->imageText->extractText($request->file('image')->getRealPath());
        } catch (\RuntimeException $e) {
            return [null, response()->json(['error' => 'ocr_failed', 'message' => $e->getMessage()], 422)];
        }

        if ($text === '') {
            return [null, response()->json([
                'error' => 'ocr_empty',
                'message' => 'Tidak ada teks yang terbaca dari gambar ini — coba screenshot yang lebih jelas, atau ketik manual.',
            ], 422)];
        }

        return [$text, null];
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
