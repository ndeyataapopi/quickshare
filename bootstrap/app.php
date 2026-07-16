<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load versioned v1 API routes
            \Illuminate\Support\Facades\Route::middleware(['api', 'throttle:api'])
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(__DIR__.'/../routes/api_v1.php');

            // Load module-level routes (legacy, unversioned)
            $modules = glob(app_path('Modules/*/Routes/api.php'));
            foreach ($modules as $routeFile) {
                $module = basename(dirname($routeFile, 2));
                $prefix = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '-$1', $module));

                \Illuminate\Support\Facades\Route::middleware('api')
                    ->prefix("api/{$prefix}")
                    ->group($routeFile);
            }

            $webModules = glob(app_path('Modules/*/Routes/web.php'));
            foreach ($webModules as $routeFile) {
                \Illuminate\Support\Facades\Route::middleware('web')
                    ->group($routeFile);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi(60);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(function (Request $request) {
            if (auth()->check()) {
                if (auth()->user()->hasRole('admin')) {
                    return route('admin.dashboard');
                }
                return route('client.dashboard');
            }
            return route('home');
        });

        $middleware->web(append: [
            \App\Http\Middleware\ThrottleIP::class . ':60,1',
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ThrottleIP::class . ':60,1',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active_user' => \App\Http\Middleware\EnsureActiveUser::class,
            'kyc_verified' => \App\Http\Middleware\CheckKYCStatus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint not found.',
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Forbidden.',
                ], 403);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'success' => false,
                    'message' => app()->isProduction() ? 'Server error.' : $e->getMessage(),
                ], $code);
            }
        });
    })->create();
