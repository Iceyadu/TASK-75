<?php
declare(strict_types=1);

namespace app\controller;

use app\service\AuthService;
use think\facade\Session;

class AuthController extends BaseController
{
    protected AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    /**
     * POST /api/auth/register
     */
    public function register()
    {
        $payload = $this->request->post();

        validate('app\validate\AuthValidate.register')->check($payload);

        $user = $this->authService->register($payload);

        return json([
            'code'    => 0,
            'message' => 'Registration successful',
            'data'    => $user->toArray(),
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login()
    {
        validate('app\validate\AuthValidate.login')->check($this->request->post());

        $result = $this->authService->login(
            $this->request->post('email'),
            $this->request->post('password'),
            $this->request->post('organization_code', ''),
            $this->request->ip(),
            $this->request->header('User-Agent', '')
        );

        return json([
            'code'    => 0,
            'message' => 'Login successful',
            'data'    => [
                'user'               => $result['user']->toArray(),
                'roles'              => $result['roles'],
                'token'              => $result['token'],
                'session_expires_at' => $result['session_expires_at'],
            ],
        ], 200);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout()
    {
        $this->authService->logout(
            (int) $this->request->user->id,
            session_id() ?: ''
        );

        return json([
            'code'    => 0,
            'message' => 'Logged out successfully',
            'data'    => null,
        ], 200);
    }

    /**
     * GET /api/auth/me
     */
    public function me()
    {
        $user = $this->authService->getCurrentUser((int) $this->request->user->id);

        $userData          = $user->toArray();
        $userData['roles'] = array_map(static function ($role): string {
            return (string) ($role['slug'] ?? '');
        }, $user->roles ? $user->roles->toArray() : []);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $userData,
        ], 200);
    }
}
