<?php

namespace app\model;

use think\Model;

class UserRole extends Model
{
    protected $table = 'user_roles';

    protected $autoWriteTimestamp = false;

    /**
     * Primary key is composite (user_id, role_id), so disable auto-increment.
     */
    protected $pk = ['user_id', 'role_id'];
}
