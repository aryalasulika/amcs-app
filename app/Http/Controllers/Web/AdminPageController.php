<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function stands(Request $request): View
    {
        abort_unless($request->user()->can('master-data.manage'), 403);

        return view('admin.stands');
    }

    public function aircraftTypes(Request $request): View
    {
        abort_unless($request->user()->can('master-data.manage'), 403);

        return view('admin.aircraft-types');
    }

    public function airlines(Request $request): View
    {
        abort_unless($request->user()->can('master-data.manage'), 403);

        return view('admin.airlines');
    }

    public function users(Request $request): View
    {
        abort_unless($request->user()->can('users.manage'), 403);

        return view('admin.users');
    }

    public function auditLogs(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        return view('admin.audit-logs');
    }
}
