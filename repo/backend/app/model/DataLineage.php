<?php

namespace app\model;

use think\Model;

class DataLineage extends Model
{
    protected $table = 'data_lineage';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    protected $type = [
        'details' => 'json',
    ];

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }
}
