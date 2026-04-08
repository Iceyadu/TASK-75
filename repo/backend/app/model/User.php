<?php

namespace app\model;

use think\Model;

class User extends Model
{
    protected $table = 'users';

    protected $autoWriteTimestamp = 'datetime';

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    protected $hidden = ['password', 'password_hash'];

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ---- Relationships ----

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'role_id', 'user_id');
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class, 'user_id', 'id');
    }

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class, 'user_id', 'id');
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'user_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id', 'id');
    }

    // ---- Permission helpers ----

    /**
     * Check whether this user has a specific permission through any of their roles.
     *
     * @param string $resource  e.g. "listing"
     * @param string $action    e.g. "create"
     * @return bool
     */
    public function hasPermission(string $resource, string $action): bool
    {
        $roles = $this->roles()->with(['permissions'])->select();

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($permission->resource === $resource && $permission->action === $action) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this user has the "admin" role.
     */
    public function isAdmin(): bool
    {
        $roles = $this->roles()->select();
        foreach ($roles as $role) {
            if ($role->name === 'admin') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this user has the "moderator" role.
     */
    public function isModerator(): bool
    {
        $roles = $this->roles()->select();
        foreach ($roles as $role) {
            if ($role->name === 'moderator') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a flat array of all "resource.action" permission strings.
     *
     * @return array
     */
    public function getPermissionsAttr(): array
    {
        $permissions = [];
        $roles = $this->roles()->with(['permissions'])->select();

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $key = $permission->resource . '.' . $permission->action;
                $permissions[$key] = true;
            }
        }

        return array_keys($permissions);
    }
}
