<?php

namespace app;

use app\exception\AuthException;
use app\exception\BusinessException;
use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\exception\RateLimitException;
use app\exception\ValidationException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\ValidateException;
use think\Response;

class ExceptionHandle extends Handle
{
    /**
     * Exception types that should not be reported/logged.
     *
     * @var array
     */
    protected $ignoreReport = [
        ValidationException::class,
        ValidateException::class,
        AuthException::class,
        ForbiddenException::class,
        NotFoundException::class,
        DataNotFoundException::class,
        ModelNotFoundException::class,
    ];

    /**
     * Report (log) the exception with masked PII.
     */
    public function report(\Throwable $exception): void
    {
        if (!$this->isIgnoreReport($exception)) {
            $request = $this->app->request;
            $safeMessage = $this->sanitizeLogMessage($exception->getMessage());

            $context = [
                'exception' => get_class($exception),
                'message'   => $safeMessage,
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
            ];

            if ($request->user ?? null) {
                $context['user'] = mask_user_id($request->user->id ?? 0);
            }

            $ip = $request->ip();
            if ($ip) {
                $context['ip'] = mask_ip($ip);
            }

            $this->app->log->error($safeMessage, $context);
        }
    }

    /**
     * Redact sensitive fragments before writing error messages to logs.
     */
    protected function sanitizeLogMessage(string $message): string
    {
        $masked = $message;

        // Mask email addresses.
        $masked = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $masked);

        // Mask bearer tokens.
        $masked = (string) preg_replace('/\bBearer\s+[A-Za-z0-9\-_\.]+\b/i', 'Bearer [redacted-token]', $masked);

        // Mask common password/secret patterns.
        $masked = (string) preg_replace('/\b(password|passwd|token|secret)\b\s*[:=]\s*([^\s,\]]+)/i', '$1=[redacted]', $masked);

        return $masked;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request, \Throwable $e): Response
    {
        // Only produce JSON envelope for API / JSON-expecting requests
        if ($this->isJsonRequest($request)) {
            return $this->renderJsonResponse($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Determine if the request expects a JSON response.
     */
    protected function isJsonRequest($request): bool
    {
        $accept = $request->header('accept', '');
        if (str_contains($accept, 'application/json') || str_contains($accept, '+json')) {
            return true;
        }

        if ($request->isAjax()) {
            return true;
        }

        // Treat all API path requests as JSON
        $pathInfo = $request->pathinfo();
        if (str_starts_with($pathInfo, 'api/') || str_starts_with($pathInfo, 'v1/')) {
            return true;
        }

        return false;
    }

    /**
     * Convert the exception to a standard JSON envelope response.
     */
    protected function renderJsonResponse(\Throwable $e): Response
    {
        // app\exception\ValidationException
        if ($e instanceof ValidationException) {
            return json([
                'code'    => 40001,
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => $e->getErrors(),
            ], 422);
        }

        // think\exception\ValidateException (ThinkPHP built-in)
        if ($e instanceof ValidateException) {
            return json([
                'code'    => 40001,
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => $e->getError(),
            ], 422);
        }

        // RateLimitException (must be checked before BusinessException)
        if ($e instanceof RateLimitException) {
            $response = json([
                'code'    => $e->getBizCode(),
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], $e->getHttpStatus());
            $response->header([
                'Retry-After' => (string) $e->getRetryAfter(),
            ]);
            return $response;
        }

        // AuthException
        if ($e instanceof AuthException) {
            return json([
                'code'    => $e->getBizCode(),
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        // ForbiddenException
        if ($e instanceof ForbiddenException) {
            return json([
                'code'    => $e->getBizCode(),
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        // NotFoundException
        if ($e instanceof NotFoundException) {
            return json([
                'code'    => $e->getBizCode(),
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], 404);
        }

        // Generic BusinessException
        if ($e instanceof BusinessException) {
            return json([
                'code'    => $e->getBizCode(),
                'message' => $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], $e->getHttpStatus());
        }

        // ThinkPHP DB not-found exceptions
        if ($e instanceof DataNotFoundException || $e instanceof ModelNotFoundException) {
            return json([
                'code'    => 40401,
                'message' => 'Resource not found',
                'data'    => null,
                'errors'  => null,
            ], 404);
        }

        // All other exceptions
        $debug   = $this->app->isDebug();
        $message = $debug ? $e->getMessage() : 'Server error';

        $payload = [
            'code'    => 50001,
            'message' => $message,
            'data'    => null,
            'errors'  => null,
        ];

        if ($debug) {
            $payload['trace'] = $e->getTraceAsString();
        }

        return json($payload, 500);
    }
}
