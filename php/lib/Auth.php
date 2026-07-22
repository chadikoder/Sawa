<?php
declare(strict_types=1);

final class Auth
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_ROLE = 'user_role';
    private const SESSION_ACCOUNTS = 'accounts';

    /** @var ?array<string, mixed> */
    private static ?array $cachedUser = null;

    /**
     * Log a user in. When $add is true the current session is preserved and the
     * new account is added alongside it (multi-account); the new account becomes
     * active. When false (normal login) any other signed-in accounts are dropped.
     */
    public static function login(int $userId, string $role, bool $add = false): void
    {
        $accounts = $add ? self::accountsRaw() : [];
        Session::regenerate();
        $accounts[$userId] = ['id' => $userId, 'role' => $role];
        Session::set(self::SESSION_ACCOUNTS, $accounts);
        Session::set(self::SESSION_USER_ID, $userId);
        Session::set(self::SESSION_ROLE, $role);
        self::$cachedUser = null;
        self::hydrateActiveMeta();
    }

    /**
     * Switch the active account to another already-signed-in account on this
     * device. Returns false if that account is not in the session.
     */
    public static function switchTo(int $userId): bool
    {
        $accounts = self::accountsRaw();
        if (!isset($accounts[$userId])) {
            return false;
        }
        Session::regenerate();
        Session::set(self::SESSION_USER_ID, $userId);
        Session::set(self::SESSION_ROLE, (string) $accounts[$userId]['role']);
        self::$cachedUser = null;
        self::hydrateActiveMeta();
        return true;
    }

    /**
     * Sign out the active account. If other accounts remain on this device the
     * session is kept and one of them becomes active (returns true). Otherwise
     * the whole session is destroyed (returns false).
     */
    public static function logout(): bool
    {
        self::$cachedUser = null;
        $accounts = self::accountsRaw();
        $current = self::id();
        if ($current !== null) {
            unset($accounts[$current]);
        }
        if ($accounts !== []) {
            $next = $accounts[array_key_first($accounts)];
            Session::regenerate();
            Session::set(self::SESSION_ACCOUNTS, $accounts);
            Session::set(self::SESSION_USER_ID, (int) $next['id']);
            Session::set(self::SESSION_ROLE, (string) $next['role']);
            return true;
        }
        Session::destroy();
        return false;
    }

    /** @return array<int, array{id:int, role:string, name:string, avatar:?string}> */
    private static function accountsRaw(): array
    {
        $a = Session::get(self::SESSION_ACCOUNTS);
        return is_array($a) ? $a : [];
    }

    /** Other signed-in accounts on this device (excludes the active one). */
    /** @return list<array{id:int, role:string, name:string, avatar:?string}> */
    public static function otherAccounts(): array
    {
        $current = self::id();
        $out = [];
        foreach (self::accountsRaw() as $acc) {
            if ((int) $acc['id'] !== $current) {
                $out[] = [
                    'id'     => (int) $acc['id'],
                    'role'   => (string) ($acc['role'] ?? 'user'),
                    'name'   => (string) ($acc['name'] ?? 'Account'),
                    'avatar' => $acc['avatar'] ?? null,
                ];
            }
        }
        return $out;
    }

    /** Cache the active user's name/avatar onto its session account entry. */
    private static function hydrateActiveMeta(): void
    {
        $id = self::id();
        if ($id === null) {
            return;
        }
        $u = self::user();
        $accounts = self::accountsRaw();
        if (isset($accounts[$id])) {
            $accounts[$id]['name'] = (string) ($u['full_name'] ?? 'Account');
            $accounts[$id]['avatar'] = $u['avatar_path'] ?? null;
            Session::set(self::SESSION_ACCOUNTS, $accounts);
        }
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_USER_ID);
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function role(): ?string
    {
        if (self::$cachedUser !== null && isset(self::$cachedUser['role'])) {
            return (string) self::$cachedUser['role'];
        }
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
            'SELECT u.*, p.bio, p.location, p.birthdate, p.gender, p.avatar_path, p.banner_path
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
        if (!self::check() || self::user() === []) {
            self::logout();
            Response::redirectStatus('pages/login.php', 'expired');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();
        $user = self::user();
        $role = isset($user['role']) ? (string) $user['role'] : self::role();
        if ($role !== null) {
            Session::set(self::SESSION_ROLE, $role);
        }
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
