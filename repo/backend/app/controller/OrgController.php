<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\NotFoundException;
use app\model\Organization;
use app\service\AuditService;

class OrgController extends BaseController
{
    protected AuditService $auditService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/org
     */
    public function show()
    {
        $org = Organization::find($this->request->orgId);
        if (!$org) {
            throw new NotFoundException('Organization not found');
        }

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $org->toArray(),
        ], 200);
    }

    /**
     * PUT /api/org
     */
    public function update()
    {
        $org = Organization::find($this->request->orgId);
        if (!$org) {
            throw new NotFoundException('Organization not found');
        }

        $allowedFields = [
            'name',
            'hotlink_allowed_domains',
            'moderation_duplicate_threshold',
            'media_watermark_enabled',
            'media_url_expiry_minutes',
        ];

        $oldValues = $org->toArray();
        $data      = $this->request->post();

        // Update top-level name if provided
        if (isset($data['name'])) {
            $org->name = $data['name'];
        }

        // Update settings JSON for the remaining fields
        $settings = $org->settings ?? [];
        $settingsFields = [
            'hotlink_allowed_domains',
            'moderation_duplicate_threshold',
            'media_watermark_enabled',
            'media_url_expiry_minutes',
        ];

        foreach ($settingsFields as $field) {
            if (array_key_exists($field, $data)) {
                $settings[$field] = $data[$field];
            }
        }

        $org->settings = $settings;
        $org->save();

        $this->auditService->log(
            $this->request->orgId,
            (int) $this->request->user->id,
            'org.update',
            'organization',
            $this->request->orgId,
            $oldValues,
            $org->toArray()
        );

        return json([
            'code'    => 0,
            'message' => 'Organization updated successfully',
            'data'    => $org->toArray(),
        ], 200);
    }
}
