<?php
declare(strict_types=1);

namespace app\service;

use app\model\BehaviorEvent;
use app\model\DataLineage;
use app\model\DataQualityMetric;

class GovernanceService
{
    /**
     * Record a behavior event.
     */
    public function recordEvent(
        int $orgId,
        ?int $userId,
        string $eventType,
        string $targetType,
        ?int $targetId,
        array $metadata = []
    ): void {
        $event = new BehaviorEvent();
        $event->organization_id = $orgId;
        $event->user_id         = $userId;
        $event->event_type      = $eventType;
        $event->target_type     = $targetType;
        $event->target_id       = $targetId;
        $event->metadata        = json_encode($metadata);
        $event->save();
    }

    /**
     * Get data quality metrics for a date range.
     */
    public function getQualityMetrics(int $orgId, string $fromDate, string $toDate): array
    {
        return DataQualityMetric::where('organization_id', $orgId)
            ->where('metric_date', '>=', $fromDate)
            ->where('metric_date', '<=', $toDate)
            ->order('metric_date', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * Get data lineage records with optional filters and pagination.
     */
    public function getLineage(int $orgId, array $filters): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        $query = DataLineage::where('organization_id', $orgId);

        if (!empty($filters['job_name'])) {
            $query->where('job_name', $filters['job_name']);
        }
        if (!empty($filters['run_id'])) {
            $query->where('run_id', $filters['run_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('executed_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('executed_at', '<=', $filters['to_date']);
        }

        $query->order('executed_at', 'desc');

        $total   = $query->count();
        $records = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        return [
            'lineage' => $records->toArray(),
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Get behavior events with optional filters and pagination.
     */
    public function getEvents(int $orgId, array $filters): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        $query = BehaviorEvent::where('organization_id', $orgId);

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $query->order('created_at', 'desc');

        $total  = $query->count();
        $events = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        return [
            'events' => $events->toArray(),
            'meta'   => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }
}
