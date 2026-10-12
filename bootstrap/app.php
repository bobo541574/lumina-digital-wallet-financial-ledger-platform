<?php

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Shared\Http\Middleware\AssignRequestId;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // The SPA authenticates with a Sanctum bearer token rather than a session
        // cookie, so channel authorization runs on the sanctum guard, not `web`.
        attributes: ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => $e->errorCode->value,
                    'message' => $e->getMessage(),
                    'details' => (object) $e->details,
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], $e->httpStatus);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => ErrorCode::ValidationFailed->value,
                    'message' => 'The given data was invalid.',
                    'details' => ['fields' => $e->errors()],
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => ErrorCode::Unauthenticated->value,
                    'message' => 'Unauthenticated.',
                    'details' => (object) [],
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], 401);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => ErrorCode::Forbidden->value,
                    'message' => 'This action is unauthorized.',
                    'details' => (object) [],
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], 403);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => ErrorCode::RateLimited->value,
                    'message' => 'Too many requests.',
                    'details' => (object) [],
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], 429, $e->getHeaders());
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => ErrorCode::ResourceNotFound->value,
                    'message' => 'Resource not found.',
                    'details' => (object) [],
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], 404);
        });
    })->create();
