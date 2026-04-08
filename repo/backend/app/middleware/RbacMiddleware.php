<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\ForbiddenException;
use think\Request;
use think\Response;

/**
 * Role-based access control middleware.
 *
 * Receives a dot-separated permission string via the route definition:
 *   ->middleware('rbac:listing.create')
 *
 * The string is split into a resource and an action, and the authenticated
 * user's permission set is checked via User::hasPermission().
 */
class RbacMiddleware
{
    /**
     * @param Request  $request
     * @param \Closure $next
     * @param string   $permission  Dot-separated "resource.action"
     * @return Response
     * @throws ForbiddenException
     */
    public function handle(Request $request, \Closure $next, string $permission): Response
    {
        [$resource, $action] = $this->parsePermission($permission);

        if (!$request->user->hasPermission($resource, $action)) {
            throw new ForbiddenException(
                "You do not have permission to {$action} {$resource}",
                40301
            );
        }

        return $next($request);
    }

    /**
     * Split a permission string into resource and action components.
     *
     * @param string $permission
     * @return array{0: string, 1: string}
     */
    private function parsePermission(string $permission): array
    {
        $parts = explode('.', $permission, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException(
                "Invalid permission format '{$permission}'. Expected 'resource.action'."
            );
        }

        return [$parts[0], $parts[1]];
    }
}
