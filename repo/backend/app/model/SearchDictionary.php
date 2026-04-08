<?php

namespace app\model;

use think\Model;

class SearchDictionary extends Model
{
    protected $table = 'search_dictionary';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }
}
