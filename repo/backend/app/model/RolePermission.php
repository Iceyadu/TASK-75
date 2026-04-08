<?php

namespace app\model;

use think\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $autoWriteTimestamp = false;

    /**
     * Primary key is composite (role_id, permission_id), so disable auto-increment.
     */
    protected $pk = ['role_id', 'permission_id'];
}
