<?php

namespace app\model;

use think\Model;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    // ---- Relationships ----

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // ---- Status helpers ----

    /**
     * Check if this session has expired.
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getData('expires_at');
        if (!$expiresAt) {
            return false;
        }
        return strtotime($expiresAt) <= time();
    }
}
