<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\GlobalSearchService;
use App\Support\SearchBackendUnavailable;
use App\Support\SearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'channel_id' => ['nullable', 'integer'],
        ]);

        try {
            SearchQuery::from($validated['q']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['q' => $exception->getMessage()]);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            return response()->json($this->search->search(
                $user,
                $validated['q'],
                channelId: isset($validated['channel_id']) ? (int) $validated['channel_id'] : null,
            ));
        } catch (SearchBackendUnavailable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Search is temporarily unavailable.',
                'results' => [],
            ], 503);
        }
    }
}
