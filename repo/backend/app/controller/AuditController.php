<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ForbiddenException;
use app\service\AuditService;

class AuditController extends BaseController
{
    protected AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/audit
     */
    public function index()
    {
        $unmask = (bool) $this->request->get('unmask', false);

        // If requesting unmasked data, verify permission
        if ($unmask) {
            if (!$this->request->user->hasPermission('audit', 'read_unmasked')) {
                throw new ForbiddenException('You do not have permission to view unmasked audit data');
            }
        }

        $filters = [
            'user_id'       => $this->request->get('user_id', ''),
            'action'        => $this->request->get('action', ''),
            'resource_type' => $this->request->get('resource_type', ''),
            'from_date'     => $this->request->get('from_date', ''),
            'to_date'       => $this->request->get('to_date', ''),
            'page'          => $this->request->get('page', 1),
            'per_page'      => $this->request->get('per_page', 15),
        ];

        $result = $this->auditService->query($this->request->orgId, $filters, $unmask);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $result['audit_logs'],
            'meta'    => $result['meta'],
        ], 200);
    }
}
