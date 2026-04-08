<?php
declare(strict_types=1);

namespace app\service;

use app\exception\AuthException;
use app\exception\BusinessException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\model\Organization;
use app\model\Role;
use app\model\User;
use app\model\UserSession;
use think\facade\Session;

class AuthService
{
    /**
     * Register a new user within an organization.
     */
    public function register(array $data): User
    {
        $org = Organization::where('code', $data['organization_code'])->find();
        if (!$org) {
            throw new NotFoundException('Organization not found', 40401);
        }

        $existing = User::where('email', $data['email'])
            ->where('organization_id', $org->id)
            ->find();
        if ($existing) {
            throw new BusinessException('Email already registered in this organization', 40901, 409);
        }

        $user = new User();
        $user->name            = $data['name'];
        $user->email           = $data['email'];
        $user->password_hash   = password_hash($data['password'], PASSWORD_BCRYPT);
        $user->organization_id = $org->id;
        $user->status          = 'active';
        $user->save();

        // Assign default 'user' role
        $role = Role::where('slug', 'user')
            ->where('organization_id', $org->id)
            ->find();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        // Create initial session record
        $session = new UserSession();
        $session->user_id   = $user->id;
        $session->session_id = session_id() ?: bin2hex(random_bytes(20));
        $session->ip_address = request()->ip();
        $session->user_agent = request()->header('User-Agent', '');
        $session->expires_at = date('Y-m-d\TH:i:s\Z', strtotime('+24 hours'));
        $session->save();

        return $user;
    }

    /**
     * Authenticate a user by email and password.
     */
    public function login(string $email, string $password, string $organizationCode, string $ip, string $userAgent): array
    {
        $org = Organization::where('code', $organizationCode)->find();
        if (!$org) {
            throw new AuthException('Invalid credentials', 40101);
        }

        $user = User::where('email', $email)
            ->where('organization_id', $org->id)
            ->find();
        if (!$user) {
            throw new AuthException('Invalid credentials', 40101);
        }

        if (!password_verify($password, $user->password_hash)) {
            throw new AuthException('Invalid credentials', 40101);
        }

        if ($user->status !== 'active') {
            throw new AuthException('Account is disabled', 40102);
        }

        $expiresAt = date('Y-m-d\TH:i:s\Z', strtotime('+24 hours'));

        // Create session record
        $session = new UserSession();
        $session->user_id   = $user->id;
        $session->session_id = session_id() ?: bin2hex(random_bytes(20));
        $session->ip_address = $ip;
        $session->user_agent = $userAgent;
        $session->expires_at = $expiresAt;
        $session->save();

        // Start PHP session
        Session::set('user_id', $user->id);

        return [
            'user'               => $user,
            'roles'              => array_map(static function ($role): string {
                return (string) ($role['slug'] ?? '');
            }, $user->roles()->select()->toArray()),
            'session_expires_at' => $expiresAt,
        ];
    }

    /**
     * Log out a user by destroying their session.
     */
    public function logout(int $userId, string $sessionId): void
    {
        UserSession::where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->delete();

        Session::clear();
    }

    /**
     * Get the current authenticated user with roles.
     */
    public function getCurrentUser(int $userId): User
    {
        $user = User::with(['roles'])->find($userId);
        if (!$user) {
            throw new NotFoundException('User not found', 40401);
        }
        return $user;
    }
}
