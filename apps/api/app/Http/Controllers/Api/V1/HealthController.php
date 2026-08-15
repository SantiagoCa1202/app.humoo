<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(Request $request)
    {
        $databaseOkay = false;

        try {
            DB::connection()->getPdo();
            $databaseOkay = true;
        } catch (\Throwable) {
            $databaseOkay = false;
        }

        return ApiResponse::success([
            'status' => $databaseOkay ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'php' => PHP_VERSION,
            'database' => [
                'driver' => config('database.default'),
                'connected' => $databaseOkay,
            ],
        ], [
            'request_id' => $request->attributes->get('request_id'),
        ], $databaseOkay ? 200 : 503);
    }
}
