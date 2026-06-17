<?php

namespace App\Http\Controllers;

use App\Services\Outreach\OutreachDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutreachDemoLoginController extends Controller
{
    public function __invoke(string $token, Request $request, OutreachDemoService $service): RedirectResponse
    {
        return $service->loginFromToken($token, $request);
    }
}
