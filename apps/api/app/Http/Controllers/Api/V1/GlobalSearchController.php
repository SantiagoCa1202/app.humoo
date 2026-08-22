<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $workspace = app('currentWorkspace');

        return response()->json([
            'data' => $search->search(
                $workspace,
                $request->user(),
                $validated['q'],
                (int) ($validated['limit'] ?? 30),
            ),
        ]);
    }
}
