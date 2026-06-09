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

if ($fullName === '' || !Validator::password($password) || $password !== $passwordConfirm) {
    Response::redirectStatus('pages/signup.php', 'error');
}

$hasEmail = $email !== '' && Validator::email($email);
$hasPhone = $phone !== '' && Validator::phone($phone);
if (!$hasEmail) {
    Response::redirectStatus('pages/signup.php', 'error');
}

if (!in_array($role, ['user', 'beneficiary', 'organisation'], true)) {
    Response::redirectStatus('pages/signup.php', 'error');
}

$age = Validator::ageFromBirthdate($birthdate);
if ($age === null || $age < 10) {
    Response::redirectStatus('pages/signup.php', 'error');
}

if (!in_array($gender, ['Male', 'Female'], true)) {
    Response::redirectStatus('pages/signup.php', 'error');
}

$pdo = db();

if ($hasEmail) {
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([strtolower($email)]);
    if ($chk->fetch()) {
        Response::redirectStatus('pages/signup.php', 'error');
    }
}

if ($hasPhone) {
    $chk = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $chk->execute([$phone]);
    if ($chk->fetch()) {
        Response::redirectStatus('pages/signup.php', 'error');
    }
}

$bio = Validator::sanitizeString((string) ($_POST['user_description'] ?? ''), 250);
$location = Validator::sanitizeString((string) ($_POST['user_location'] ?? ''), 80);
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO users (email, phone, password_hash, full_name, role, email_verified)
         VALUES (?, ?, ?, ?, ?, 0)'
    );
    $stmt->execute([
        $hasEmail ? strtolower($email) : null,
        $hasPhone ? $phone : null,
        $hash,
        $fullName,
        $role,
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
    if (APP_ENV === 'development') {
        error_log($e->getMessage());
    }
    Response::redirectStatus('pages/signup.php', 'error');
}

if ($hasEmail) {
    Mailer::sendVerification(strtolower($email), $token);
}

Auth::login($userId, $role);

if ($role === 'organisation') {
    Response::redirect('pages/org-pending.html');
}

Response::redirect('pages/loading.html', ['next' => 'pages/userhome.php']);
