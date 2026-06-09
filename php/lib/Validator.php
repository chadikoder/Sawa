<?php
declare(strict_types=1);

final class Validator
{
    public const EMAIL_RE = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    public const PHONE_RE = '/^(?:\+961|00961|0)[0-9]{6,11}$/';
    public const PASSWORD_RE = '/^(?=.*[A-Za-z])(?=.*\d).{6,}$/';
    public const URL_RE = '/^https?:\/\/.+/i';

    public static function email(string $value): bool
    {
        return (bool) preg_match(self::EMAIL_RE, $value);
    }

    public static function phone(string $value): bool
    {
        return (bool) preg_match(self::PHONE_RE, $value);
    }

    public static function password(string $value): bool
    {
        return (bool) preg_match(self::PASSWORD_RE, $value);
    }

    public static function url(string $value): bool
    {
        return (bool) preg_match(self::URL_RE, $value);
    }

    public static function sanitizeString(string $value, int $maxLen): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }
        return $value;
    }

    public static function ageFromBirthdate(string $date): ?int
    {
        $dob = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dob) {
            return null;
        }
        $today = new DateTime('today');
        return (int) $today->diff($dob)->y;
    }
}
