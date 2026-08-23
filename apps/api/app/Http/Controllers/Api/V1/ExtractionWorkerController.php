<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ExtractionWorkerJobService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExtractionRunResource;
use App\Models\ExtractionRun;
use Illuminate\Http\Request;

class ExtractionWorkerController extends Controller
{
    public function claim(Request $request, ExtractionWorkerJobService $service)
    {
        $job = $service->claim($this->workerId($request));

        return response()->json(['data' => $job]);
    }

    public function heartbeat(Request $request, ExtractionRun $run, ExtractionWorkerJobService $service)
    {
        $updated = $service->heartbeat($run, $this->workerId($request));

        return response()->json(['data' => new ExtractionRunResource($updated)]);
    }

    public function download(Request $request, ExtractionRun $run, ExtractionWorkerJobService $service)
    {
        return $service->download($run, $this->workerId($request));
    }

    public function result(Request $request, ExtractionRun $run, ExtractionWorkerJobService $service)
    {
        $payload = $request->validate([
            'result' => ['required', 'array'],
        ]);
        $updated = $service->submitResult($run, $this->workerId($request), $payload['result']);

        return response()->json(['data' => new ExtractionRunResource($updated)]);
    }

    public function failure(Request $request, ExtractionRun $run, ExtractionWorkerJobService $service)
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:1000'],
            'retryable' => ['sometimes', 'boolean'],
        ]);
        $updated = $service->submitFailure($run, $this->workerId($request), $payload);

        return response()->json(['data' => new ExtractionRunResource($updated)]);
    }

    private function workerId(Request $request): string
    {
        return (string) $request->attributes->get('extraction_worker_id');
    }
}
