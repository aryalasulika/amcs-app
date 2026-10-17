<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('stands.view'), 403);

        return view('dashboard', [
            'airport' => config('amcs.airport'),
            'pollSeconds' => config('amcs.realtime.poll_seconds'),
            'delayThreshold' => config('amcs.delay_threshold_minutes'),
        ]);
    }
}
