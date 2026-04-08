<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\model\Role;
use app\model\User;
use app\service\AuditService;

class UserController extends BaseController
{
    protected AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/users
     */
    public function index()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = min(100, max(1, (int) $this->request->get('per_page', 15)));

        $query = User::where('organization_id', $this->request->orgId)
            ->with(['roles'])
            ->order('created_at', 'desc');

        $total    = $query->count();
        $users    = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        // Map users to include roles as badges
        $usersArray = [];
        foreach ($users as $user) {
            $userData           = $user->toArray();
            $userData['badges'] = $user->roles ? $user->roles->column('slug') : [];
            $usersArray[]       = $userData;
        }

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $usersArray,
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ], 200);
    }

    /**
     * GET /api/users/:id
     */
    public function show($id)
    {
        $user = User::with(['roles'])
            ->where('organization_id', $this->request->orgId)
            ->find($id);

        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $userData           = $user->toArray();
        $userData['badges'] = $user->roles ? $user->roles->column('slug') : [];

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $userData,
        ], 200);
    }

    /**
     * PUT /api/users/:id/roles
     */
    public function updateRoles($id)
    {
        $user = User::where('organization_id', $this->request->orgId)->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $roleIds = $this->request->post('roles', []);
        if (!is_array($roleIds)) {
            throw new ValidationException('Roles must be an array', [
                'roles' => 'Please provide an array of role IDs',
            ]);
        }

        // Verify all role IDs belong to this org
        $validRoles = Role::where('organization_id', $this->request->orgId)
            ->whereIn('id', $roleIds)
            ->column('id');

        $invalidIds = array_diff($roleIds, $validRoles);
        if (!empty($invalidIds)) {
            throw new ValidationException('Invalid role IDs', [
                'roles' => 'The following role IDs are invalid: ' . implode(', ', $invalidIds),
            ]);
        }

        $oldRoles = $user->roles ? $user->roles->column('id') : [];

        // Sync roles (detach all, attach new)
        $user->roles()->detach();
        if (!empty($validRoles)) {
            $user->roles()->attach($validRoles);
        }

        $this->auditService->log(
            $this->request->orgId,
            (int) $this->request->user->id,
            'user.update_roles',
            'user',
            (int) $id,
            ['roles' => $oldRoles],
            ['roles' => $validRoles]
        );

        // Reload
        $user = User::with(['roles'])->find($id);

        return json([
            'code'    => 0,
            'message' => 'User roles updated successfully',
            'data'    => $user->toArray(),
        ], 200);
    }

    /**
     * POST /api/users/:id/disable
     */
    public function disable($id)
    {
        $user = User::where('organization_id', $this->request->orgId)->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $user->status = 'disabled';
        $user->save();

        $this->auditService->log(
            $this->request->orgId,
            (int) $this->request->user->id,
            'user.disable',
            'user',
            (int) $id,
            ['status' => 'active'],
            ['status' => 'disabled']
        );

        return json([
            'code'    => 0,
            'message' => 'User disabled successfully',
            'data'    => $user->toArray(),
        ], 200);
    }

    /**
     * POST /api/users/:id/enable
     */
    public function enable($id)
    {
        $user = User::where('organization_id', $this->request->orgId)->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $user->status = 'active';
        $user->save();

        $this->auditService->log(
            $this->request->orgId,
            (int) $this->request->user->id,
            'user.enable',
            'user',
            (int) $id,
            ['status' => 'disabled'],
            ['status' => 'active']
        );

        return json([
            'code'    => 0,
            'message' => 'User enabled successfully',
            'data'    => $user->toArray(),
        ], 200);
    }
}
