<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureFirstPartyRequests();
    }

    /**
     * Make the application deployable at the domain root *or* inside a
     * sub-directory, on any host/port, without editing APP_URL.
     *
     * - Forcing the URL root from the current request keeps asset() and
     *   route() URLs correct (e.g. /amcs-app/public/... when served from a
     *   sub-folder, or on 127.0.0.1:8123 when served with `artisan serve`).
     * - Trusting the current host for Sanctum stateful requests lets the
     *   first-party dashboard/ATC monitor authenticate API calls with the
     *   browser session cookie instead of returning 401.
     */
    protected function configureFirstPartyRequests(): void
    {
        if ($this->app->runningInConsole() || ! $this->app->bound('request')) {
            return;
        }

        $request = $this->app['request'];

        if (! $request->getHost()) {
            return;
        }

        URL::forceRootUrl($request->getSchemeAndHttpHost().$request->getBaseUrl());

        $stateful = array_filter(array_unique(array_merge(
            (array) config('sanctum.stateful', []),
            [$request->getHost(), $request->getHttpHost()],
        )));

        config(['sanctum.stateful' => array_values($stateful)]);
    }
}
