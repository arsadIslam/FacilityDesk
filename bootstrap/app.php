<?php

use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Laravel sorts route middleware by priority, which would otherwise run
        // "auth" and SubstituteBindings (from the web group) before the tenant is
        // known: users and route-model bindings would resolve unscoped.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdentifyTenant::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureTenantIsActive::class);

        // Both resolve on the current subdomain thanks to IdentifyTenant's URL defaults.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API always answers in JSON ({ "message", "errors" }), even when a
        // client forgets the Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Never reveal model class names or ids: cross-tenant records must be
        // indistinguishable from records that do not exist.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });
    })->create();
