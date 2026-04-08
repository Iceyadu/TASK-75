<?php
declare(strict_types=1);

namespace app\controller;

use app\service\TokenService;

class TokenController extends BaseController
{
    protected TokenService $tokenService;

    public function __construct()
    {
        parent::__construct();
        $this->tokenService = new TokenService();
    }

    /**
     * POST /api/tokens
     */
    public function store()
    {
        validate('app\validate\TokenValidate.create')->check($this->request->post());

        $result = $this->tokenService->create(
            (int) $this->request->user->id,
            $this->request->post('name'),
            (int) $this->request->post('expires_in_days', 90)
        );

        $data = $result['token']->toArray();
        $data['plaintext_token'] = $result['plaintext_token'];

        return json([
            'code'    => 0,
            'message' => 'Token created successfully',
            'data'    => $data,
        ], 201);
    }

    /**
     * GET /api/tokens
     */
    public function index()
    {
        $tokens = $this->tokenService->listForUser((int) $this->request->user->id);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $tokens->toArray(),
        ], 200);
    }

    /**
     * DELETE /api/tokens/:id
     */
    public function destroy($id)
    {
        $this->tokenService->revoke((int) $id, (int) $this->request->user->id);

        return json([
            'code'    => 0,
            'message' => 'Token revoked successfully',
            'data'    => null,
        ], 200);
    }

    /**
     * POST /api/tokens/:id/rotate
     */
    public function rotate($id)
    {
        $result = $this->tokenService->rotate((int) $id, (int) $this->request->user->id);

        $data = $result['token']->toArray();
        $data['plaintext_token'] = $result['plaintext_token'];

        return json([
            'code'    => 0,
            'message' => 'Token rotated successfully',
            'data'    => $data,
        ], 200);
    }
}
