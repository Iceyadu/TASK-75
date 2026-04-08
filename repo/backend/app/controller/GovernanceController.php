<?php
declare(strict_types=1);

namespace app\controller;

use app\service\GovernanceService;

class GovernanceController extends BaseController
{
    protected GovernanceService $governanceService;

    public function __construct()
    {
        $this->governanceService = new GovernanceService();
    }

    /**
     * GET /api/governance/metrics?from_date=...&to_date=...
     */
    public function metrics()
    {
        $fromDate = $this->request->get('from_date', date('Y-m-d', strtotime('-30 days')));
        $toDate   = $this->request->get('to_date', date('Y-m-d'));

        $metrics = $this->governanceService->getQualityMetrics(
            $this->request->orgId,
            $fromDate,
            $toDate
        );

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $metrics,
        ], 200);
    }

    /**
     * GET /api/governance/lineage
     */
    public function lineage()
    {
        $filters = [
            'job_name'  => $this->request->get('job_name', ''),
            'run_id'    => $this->request->get('run_id', ''),
            'from_date' => $this->request->get('from_date', ''),
            'to_date'   => $this->request->get('to_date', ''),
            'page'      => $this->request->get('page', 1),
            'per_page'  => $this->request->get('per_page', 15),
        ];

        $result = $this->governanceService->getLineage($this->request->orgId, $filters);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $result['lineage'],
            'meta'    => $result['meta'],
        ], 200);
    }

    /**
     * GET /api/governance/events
     */
    public function events()
    {
        $filters = [
            'event_type'  => $this->request->get('event_type', ''),
            'target_type' => $this->request->get('target_type', ''),
            'user_id'     => $this->request->get('user_id', ''),
            'from_date'   => $this->request->get('from_date', ''),
            'to_date'     => $this->request->get('to_date', ''),
            'page'        => $this->request->get('page', 1),
            'per_page'    => $this->request->get('per_page', 15),
        ];

        $result = $this->governanceService->getEvents($this->request->orgId, $filters);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $result['events'],
            'meta'    => $result['meta'],
        ], 200);
    }
}
