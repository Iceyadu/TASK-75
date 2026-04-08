<?php

namespace app\model;

use think\Model;

class Organization extends Model
{
    protected $table = 'organizations';

    protected $autoWriteTimestamp = 'datetime';

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    protected $type = [
        'settings' => 'json',
    ];

    // ---- Relationships ----

    public function users()
    {
        return $this->hasMany(User::class, 'organization_id', 'id');
    }

    public function roles()
    {
        return $this->hasMany(Role::class, 'organization_id', 'id');
    }

    // ---- Accessors ----

    /**
     * Ensure settings always returns an array even when stored as null.
     */
    public function getSettingsAttr($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return is_array($value) ? $value : [];
    }
}
