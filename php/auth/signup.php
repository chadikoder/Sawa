<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/signup.php');
}

Csrf::validate();

$fullName = Validator::sanitizeString((string) ($_POST['full_name'] ?? ''), 120);
$email = trim((string) ($_POST['user_email'] ?? ''));
$phone = trim((string) ($_POST['user_phone'] ?? ''));
$password = (string) ($_POST['user_password'] ?? '');
$passwordConfirm = (string) ($_POST['user_password_confirm'] ?? '');
$birthdate = (string) ($_POST['user_birthdate'] ?? '');
$gender = (string) ($_POST['user_gender'] ?? '');
$role = (string) ($_POST['user_role'] ?? '');

// Specific error codes so the signup page can surface a real message instead
// of a mystery redirect. See pages/signup.php for the ?error=<code> mapping.
$fail = static fn (string $code): never => Response::redirect('pages/signup.php', ['error' => $code]);

if ($fullName === '') {
    $fail('missing_name');
}
if (!Validator::password($password)) {
    $fail('weak_password');
}
if ($password !== $passwordConfirm) {
    $fail('password_mismatch');
}

$hasEmail = $email !== '' && Validator::email($email);
$hasPhone = $phone !== '' && Validator::phone($phone);
if (!$hasEmail) {
    $fail('invalid_email');
}

if (!in_array($role, ['user', 'beneficiary', 'organisation'], true)) {
    $fail('invalid_role');
}

$age = Validator::ageFromBirthdate($birthdate);
if ($age === null || $age < 10) {
    $fail('invalid_age');
}

if (!in_array($gender, ['Male', 'Female', 'Other', 'Prefer not to say'], true)) {
    $fail('invalid_gender');
}

$pdo = db();

if ($hasEmail) {
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([strtolower($email)]);
    if ($chk->fetch()) {
        $fail('email_taken');
    }
}

if ($hasPhone) {
    $chk = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $chk->execute([$phone]);
    if ($chk->fetch()) {
        $fail('phone_taken');
    }
}

// The form carries two bio boxes — one on the personal step, one on the
// organisation step — and both live inside the same <form>. A hidden step is
// only display:none, so its fields are still submitted; when both were named
// "user_description" the later (empty) one silently won and the bio the user
// typed never reached the database. They now have distinct names.
$bioPersonal = Validator::sanitizeString((string) ($_POST['user_description'] ?? ''), 250);
$bioOrg = Validator::sanitizeString((string) ($_POST['org_description'] ?? ''), 250);
$bio = ($role === 'organisation' && $bioOrg !== '') ? $bioOrg : $bioPersonal;
$location = Validator::sanitizeString((string) ($_POST['user_location'] ?? ''), 80);
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // Signup logs the new account straight in, but php/auth/login.php refuses
    // any account with email_verified = 0. On a stock local install PHP's
    // mail() has no SMTP to hand off to, so the verification email never
    // arrives — which meant signing up, being let in, and then being locked
    // out permanently the moment you logged out, with no way back. That is
    // exactly the path someone running this from a handed-over folder takes.
    //
    // Outside production the account is therefore created already verified.
    // Production keeps the real flow, and the token below is still issued in
    // both cases so the verify link works wherever mail is actually delivered.
    $emailVerified = APP_ENV === 'production' ? 0 : 1;

    $stmt = $pdo->prepare(
        'INSERT INTO users (email, phone, password_hash, full_name, role, email_verified)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $hasEmail ? strtolower($email) : null,
        $hasPhone ? $phone : null,
        $hash,
        $fullName,
        $role,
        $emailVerified,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $avatarPath = null;
    if (!empty($_FILES['profile_image']['tmp_name'])) {
        $avatarPath = Upload::store($_FILES['profile_image'], 'profiles/' . $userId);
    } elseif (!empty($_FILES['org_image']['tmp_name'])) {
        $avatarPath = Upload::store($_FILES['org_image'], 'profiles/' . $userId);
    }

    $pdo->prepare(
        'INSERT INTO user_profiles (user_id, bio, location, birthdate, gender, avatar_path)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        $bio !== '' ? $bio : null,
        $location !== '' ? $location : null,
        $birthdate,
        $gender,
        $avatarPath,
    ]);

    $token = gen_token(32);
    $expires = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $token, $expires]);

    if ($role === 'organisation') {
        $orgName = Validator::sanitizeString((string) ($_POST['organisation_name'] ?? $fullName), 200);
        $orgPhone = trim((string) ($_POST['user_contact'] ?? ''));
        $website = trim((string) ($_POST['user_website'] ?? ''));
        $regDoc = null;
        if (!empty($_FILES['org_registration']['tmp_name'])) {
            $regDoc = Upload::store($_FILES['org_registration'], 'organisations/' . $userId, true);
        }
        $pdo->prepare(
            'INSERT INTO organisations (user_id, name, description, phone, website, logo_path, registration_doc)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $orgName,
            $bio !== '' ? $bio : null,
            Validator::phone($orgPhone) ? $orgPhone : null,
            $website !== '' && Validator::url($website) ? $website : null,
            $avatarPath,
            $regDoc,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Always log the raw exception (regardless of env) so ops can trace real
    // failures. The user gets a generic error code — never leak DB details.
    error_log('[Sawa signup] ' . $e->getMessage());
    Response::redirect('pages/signup.php', ['error' => 'server']);
}

if ($hasEmail) {
    Mailer::sendVerification(strtolower($email), $token);
}

Auth::login($userId, $role);

if ($role === 'organisation') {
    Response::redirect('pages/org-pending.html');
}

Response::redirect('pages/loading.html', ['next' => 'userhome.php']);
