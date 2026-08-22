<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateAccountRequest;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => $request->user(),
        ]);
    }

    public function update(UpdateAccountRequest $request)
    {
        $user = $request->user();
        $user->forceFill($request->validated())->save();

        return response()->json([
            'data' => $user->fresh(),
        ]);
    }
}
