<?php

namespace app\model;

use think\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $autoWriteTimestamp = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * State machine: maps current status to allowed next statuses.
     */
    protected const STATE_TRANSITIONS = [
        'pending_match' => ['accepted', 'canceled', 'expired'],
        'accepted'      => ['in_progress', 'canceled'],
        'in_progress'   => ['completed'],
        'completed'     => ['disputed'],
        'canceled'      => [],
        'expired'       => [],
        'disputed'      => ['resolved'],
        'resolved'      => [],
    ];

    // ---- Scopes ----

    public function scopeOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ---- Relationships ----

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id', 'id');
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id', 'id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'order_id', 'id');
    }

    // ---- State helpers ----

    /**
     * Get the list of valid next statuses from the current status.
     * Also considers timing rules (e.g., auto-expiry).
     *
     * @return array
     */
    public function getAllowedTransitions(): array
    {
        $current = $this->getData('status');
        $allowed = self::STATE_TRANSITIONS[$current] ?? [];

        // If pending_match and past expires_at, only expired is valid
        if ($current === 'pending_match' && $this->getData('expires_at')) {
            $expiresAt = strtotime($this->getData('expires_at'));
            if ($expiresAt && $expiresAt <= time()) {
                return ['expired'];
            }
        }

        return $allowed;
    }

    /**
     * Map next statuses into frontend action verbs.
     *
     * @return array
     */
    public function getAllowedActions(): array
    {
        $statusToAction = [
            'accepted'    => 'accept',
            'in_progress' => 'start',
            'completed'   => 'complete',
            'canceled'    => 'cancel',
            'disputed'    => 'dispute',
            'resolved'    => 'resolve',
            'expired'     => 'expire',
        ];

        $actions = [];
        foreach ($this->getAllowedTransitions() as $nextStatus) {
            if (isset($statusToAction[$nextStatus])) {
                $actions[] = $statusToAction[$nextStatus];
            }
        }

        return array_values(array_unique($actions));
    }

    /**
     * Check if a given user is a party (passenger or driver) of this order.
     *
     * @param int $userId
     * @return bool
     */
    public function isParty(int $userId): bool
    {
        return (int) $this->getData('passenger_id') === $userId
            || (int) $this->getData('driver_id') === $userId;
    }
}
