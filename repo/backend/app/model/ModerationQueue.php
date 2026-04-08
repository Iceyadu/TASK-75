<?php

namespace app\model;

use think\Model;

class ModerationQueue extends Model
{
    protected $table = 'moderation_queue';

    protected $autoWriteTimestamp = 'datetime';

    protected $dateFormat = 'Y-m-d H:i:s';

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ---- Relationships ----

    public function actions()
    {
        return $this->hasMany(ModerationAction::class, 'queue_id', 'id');
    }
}
