<?php
declare(strict_types=1);

namespace app\service;

use app\model\AuditLog;

class AuditService
{
    /**
     * Fields to strip from audit log old/new values for security.
     */
    private const SENSITIVE_FIELDS = ['password', 'password_hash', 'token_hash', 'password_confirmation'];

    /**
     * Create an audit log entry.
     */
    public function log(
        int $orgId,
        int $userId,
        string $action,
        string $resourceType,
        ?int $resourceId,
        $oldValue = null,
        $newValue = null
    ): void {
        $entry = new AuditLog();
        $entry->organization_id = $orgId;
        $entry->user_id         = $userId;
        $entry->action          = $action;
        $entry->resource_type   = $resourceType;
        $entry->resource_id     = $resourceId;
        $entry->old_value       = $oldValue !== null ? json_encode($this->sanitize($oldValue)) : null;
        $entry->new_value       = $newValue !== null ? json_encode($this->sanitize($newValue)) : null;
        $entry->ip_address      = request()->ip();
        $entry->save();
    }

    /**
     * Query audit logs with filters and pagination.
     */
    public function query(int $orgId, array $filters, bool $unmask = false): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        $query = AuditLog::where('organization_id', $orgId);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $query->order('created_at', 'desc');

        $total    = $query->count();
        $logs     = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        $logsArray = [];
        foreach ($logs as $log) {
            if ($unmask) {
                $logsArray[] = $log->toUnmaskedArray();
            } else {
                $logsArray[] = $log->toMaskedArray();
            }
        }

        return [
            'audit_logs' => $logsArray,
            'meta'       => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Strip sensitive fields from values before storing.
     */
    private function sanitize($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = $value;
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $sanitized)) {
                unset($sanitized[$field]);
            }
        }

        return $sanitized;
    }
}
