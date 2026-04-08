<?php

namespace app\model;

use think\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    // ---- Relationships ----

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'role_id', 'permission_id');
    }
}
