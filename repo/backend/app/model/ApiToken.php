<?php

namespace app\model;

use think\Model;

class ApiToken extends Model
{
    protected $table = 'api_tokens';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $hidden = ['token_hash'];

    // ---- Relationships ----

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // ---- Status helpers ----

    /**
     * Check if this token has passed its expiration date.
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getData('expires_at');
        if (!$expiresAt) {
            return false;
        }
        return strtotime($expiresAt) <= time();
    }

    /**
     * Check if this token has been explicitly revoked.
     */
    public function isRevoked(): bool
    {
        return !empty($this->getData('revoked_at'));
    }

    /**
     * Check if this token is currently valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }
}
