<?php

namespace App\Http\Controllers;

use App\Services\Outreach\OutreachDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutreachDemoIntroDismissController extends Controller
{
    public function __invoke(Request $request, OutreachDemoService $service): JsonResponse
    {
        $service->dismissDemoIntroForAuthenticatedUser($request);

        return response()->json(['dismissed' => true]);
    }
}
