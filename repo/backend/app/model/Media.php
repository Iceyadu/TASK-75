<?php

namespace app\model;

use think\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ---- Relationships ----

    /**
     * Polymorphic parent: resolves to the appropriate model based on parent_type.
     */
    public function parent()
    {
        $morphMap = [
            'review'  => Review::class,
            'listing' => Listing::class,
        ];

        $parentType = $this->getData('parent_type');
        $modelClass = $morphMap[$parentType] ?? null;

        if ($modelClass) {
            return $this->belongsTo($modelClass, 'parent_id', 'id');
        }

        return null;
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
