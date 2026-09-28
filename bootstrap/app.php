<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Render Trusted Proxy
        |--------------------------------------------------------------------------
        |
        | Render terminates HTTPS at its proxy and forwards traffic to the
        | Docker container over HTTP. Trust the proxy so Laravel uses the
        | original X-Forwarded-Proto header and generates HTTPS URLs.
        |
        */

        $middleware->trustProxies(at: '*');


        /*
        |--------------------------------------------------------------------------
        | Sanctum
        |--------------------------------------------------------------------------
        */

        $middleware->statefulApi();


        /*
        |--------------------------------------------------------------------------
        | Cookie Encryption Exceptions
        |--------------------------------------------------------------------------
        */

        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Web Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Guest Redirect
        |--------------------------------------------------------------------------
        */

        $middleware->redirectGuestsTo(
            function (Request $request): string {

                if (
                    $request->is('university-admin') ||
                    $request->is('university-admin/*')
                ) {
                    return route('university-admin.login');
                }

                if (
                    $request->is('instructor-coordinator') ||
                    $request->is('instructor-coordinator/*')
                ) {
                    return route('instructor-coordinator.login');
                }

                if (
                    $request->is('superadmin') ||
                    $request->is('superadmin/*')
                ) {
                    return route('superadmin.login');
                }

                return route('role');
            }
        );

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        //

    })
    ->create();