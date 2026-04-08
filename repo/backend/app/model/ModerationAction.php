<?php

namespace app\model;

use think\Model;

class ModerationAction extends Model
{
    protected $table = 'moderation_actions';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    // ---- Relationships ----

    public function queue()
    {
        return $this->belongsTo(ModerationQueue::class, 'queue_id', 'id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id', 'id');
    }
}
