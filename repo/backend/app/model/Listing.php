<?php

namespace app\model;

use think\Model;

class Listing extends Model
{
    protected $table = 'listings';

    protected $autoWriteTimestamp = 'datetime';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $type = [
        'tags' => 'json',
    ];

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ---- Relationships ----

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function versions()
    {
        return $this->hasMany(ListingVersion::class, 'listing_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'listing_id', 'id');
    }
}
