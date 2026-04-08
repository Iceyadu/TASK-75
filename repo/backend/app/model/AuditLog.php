<?php

namespace app\model;

use think\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d\TH:i:s\Z';

    protected $type = [
        'old_value' => 'json',
        'new_value' => 'json',
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

    // ---- PII Masking ----

    /**
     * Return array representation with PII fields masked.
     *
     * @return array
     */
    public function toMaskedArray(): array
    {
        $data = $this->toArray();

        if (isset($data['user_id'])) {
            $data['user_id'] = mask_user_id($data['user_id']);
        }

        if (isset($data['ip_address'])) {
            $data['ip_address'] = mask_ip($data['ip_address']);
        }

        return $data;
    }

    /**
     * Return array representation with all fields unmasked.
     * Should only be used when the requester has audit.read_unmasked permission.
     *
     * @return array
     */
    public function toUnmaskedArray(): array
    {
        return $this->toArray();
    }
}
