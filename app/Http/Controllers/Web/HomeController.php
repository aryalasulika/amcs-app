<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /**
     * Send signed-in users to the dashboard and guests to the login page.
     * Kept in a controller so route caching can be enabled in production.
     */
    public function index(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
