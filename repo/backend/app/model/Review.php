<?php

namespace app\model;

use think\Model;

class Review extends Model
{
    protected $table = 'reviews';

    protected $autoWriteTimestamp = 'datetime';

    protected $dateFormat = 'Y-m-d H:i:s';

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ---- Relationships ----

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Polymorphic relationship to media files attached to this review.
     */
    public function media()
    {
        return $this->hasMany(Media::class, 'parent_id', 'id')
            ->where('parent_type', 'review');
    }
}
