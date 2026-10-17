<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportPageController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('reports.view'), 403);

        return view('reports', [
            'airport' => config('amcs.airport'),
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }
}
