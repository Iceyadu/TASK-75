<?php

namespace app\model;

use think\Model;

class BehaviorEvent extends Model
{
    protected $table = 'behavior_events';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $type = [
        'metadata' => 'json',
    ];

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }
}
