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

    /**
     * Age in whole years, or null when the date is not a usable birthdate.
     *
     * Two things this has to get right and previously did not:
     *
     * - DateInterval::$y is an absolute magnitude, so a date in the *future*
     *   produced a positive age. A birthdate of 2050 came back as a plausible
     *   number and sailed through the `>= 10` check in signup.
     * - createFromFormat without a leading '!' leaves the unspecified time
     *   fields set to the current time, and it happily rolls invalid dates
     *   over (2026-02-31 becomes 2026-03-03). Resetting the time and then
     *   round-tripping the value rejects dates that never existed.
     */
    public static function ageFromBirthdate(string $date): ?int
    {
        $dob = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dob || $dob->format('Y-m-d') !== $date) {
            return null;
        }

        $today = new DateTime('today');
        if ($dob > $today) {
            return null;
        }

        return (int) $dob->diff($today)->y;
    }
}
