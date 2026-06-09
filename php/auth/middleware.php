<?php
declare(strict_types=1);

/**
 * Session-based auth middleware for protected endpoints.
 *
 *   require __DIR__ . '/../bootstrap.php';
 *   require __DIR__ . '/middleware.php';
 *   require_auth();
 *   require_role('organisation');
 */

function require_auth(): void
{
    Auth::requireAuth();
}

function require_role(string ...$roles): void
{
    Auth::requireRole(...$roles);
}

function require_organisation(): void
{
    Auth::requireRole('organisation');
    if (!Auth::isOrganisationVerified()) {
        Response::redirect('pages/org-pending.html');
    }
}

/** @return array<string, mixed> */
function current_user(): array
{
    return Auth::user();
}
