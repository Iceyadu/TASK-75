<?php

namespace app\model;

use think\Model;

class DataQualityMetric extends Model
{
    protected $table = 'data_quality_metrics';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $type = [
        'details' => 'json',
    ];

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }
}
