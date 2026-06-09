<?php
declare(strict_types=1);

final class Auth
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_ROLE = 'user_role';

    /** @var ?array<string, mixed> */
    private static ?array $cachedUser = null;

    public static function login(int $userId, string $role): void
    {
        Session::regenerate();
        Session::set(self::SESSION_USER_ID, $userId);
        Session::set(self::SESSION_ROLE, $role);
        self::$cachedUser = null;
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        Session::destroy();
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_USER_ID);
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function role(): ?string
    {
        $role = Session::get(self::SESSION_ROLE);
        return is_string($role) ? $role : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    /** @return array<string, mixed> */
    public static function user(): array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = self::id();
        if ($id === null) {
            return [];
        }
        $stmt = db()->prepare(
            'SELECT u.*, p.bio, p.location, p.birthdate, p.gender, p.avatar_path
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.id = ? AND u.active = 1
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        self::$cachedUser = $row ?: [];
        return self::$cachedUser;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            Response::redirectStatus('pages/login.html', 'expired');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();
        $role = self::role();
        if ($role === null || !in_array($role, $roles, true)) {
            Response::abort(403);
        }
    }

    /** Map DB role to front-end body class suffix */
    public static function bodyRoleClass(): string
    {
        return match (self::role()) {
            'user'          => 'role-donor',
            'beneficiary'   => 'role-taker',
            'organisation'  => 'role-org',
            'admin'         => 'role-org',
            default         => 'role-donor',
        };
    }

    public static function isOrganisationVerified(): bool
    {
        if (self::role() !== 'organisation') {
            return false;
        }
        $stmt = db()->prepare(
            'SELECT verified FROM organisations WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([self::id()]);
        $row = $stmt->fetch();
        return $row && (int) $row['verified'] === 1;
    }
}
