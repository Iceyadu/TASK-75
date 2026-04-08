<?php

namespace app\model;

use think\Model;

class ListingVersion extends Model
{
    protected $table = 'listing_versions';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $type = [
        'snapshot' => 'json',
    ];

    // ---- Relationships ----

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
