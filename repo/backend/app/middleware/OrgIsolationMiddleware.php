<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use think\Request;
use think\Response;

/**
 * Organization isolation middleware.
 *
 * Ensures that resource access is scoped to the authenticated user's
 * organization.  While model-level scopeOrg() handles query filtering, this
 * middleware provides a defence-in-depth check for single-resource routes
 * (e.g. GET /api/listings/:id) by verifying that the target record belongs
 * to the same org as the requesting user.
 */
class OrgIsolationMiddleware
{
    /**
     * Map of route prefix segments to their corresponding model classes.
     */
    private const RESOURCE_MODEL_MAP = [
        'listings'   => \app\model\Listing::class,
        'orders'     => \app\model\Order::class,
        'reviews'    => \app\model\Review::class,
        'media'      => \app\model\Media::class,
        'users'      => \app\model\User::class,
    ];

    /**
     * @param Request  $request
     * @param \Closure $next
     * @return Response
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Ensure orgId is always set.
        if (empty($request->orgId) && $request->user) {
            $request->orgId = (int) $request->user->organization_id;
        }

        // Attempt to verify the resource belongs to the user's org.
        $resourceId = $request->route('id');

        if ($resourceId !== null && $request->orgId) {
            $modelClass = $this->resolveModel($request);

            if ($modelClass !== null) {
                $record = $modelClass::find($resourceId);

                if ($record === null) {
                    throw new NotFoundException('Resource not found', 40401);
                }

                // Only check organization_id if the model actually has one.
                if (isset($record->organization_id)
                    && (int) $record->organization_id !== $request->orgId
                ) {
                    throw new ForbiddenException(
                        'You do not have access to this resource',
                        40302
                    );
                }
            }
        }

        return $next($request);
    }

    /**
     * Determine the model class based on the current request path.
     *
     * @param Request $request
     * @return string|null  Fully-qualified model class name, or null.
     */
    private function resolveModel(Request $request): ?string
    {
        $path = trim($request->pathinfo(), '/');

        foreach (self::RESOURCE_MODEL_MAP as $prefix => $modelClass) {
            if (strpos($path, "api/{$prefix}") !== false) {
                return $modelClass;
            }
        }

        return null;
    }
}
