<?php
declare(strict_types=1);

final class Format
{
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? 'U';
        $second = $parts[1] ?? '';
        return strtoupper(substr($first, 0, 1) . substr($second, 0, 1));
    }

    public static function timeAgo(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'now';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . 'm';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . 'h';
        }
        if ($diff < 604800) {
            return date('D', $ts);
        }
        return date('M j', $ts);
    }

    public static function donationStatusClass(string $status): string
    {
        return match ($status) {
            'completed' => 'status-delivered',
            'verified'  => 'status-delivered',
            'pending'   => 'status-transit',
            default     => 'status-transit',
        };
    }

    public static function donationStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Delivered',
            'verified'  => 'Delivered',
            'pending'   => 'In Transit',
            'refunded'  => 'Refunded',
            'failed'    => 'Failed',
            default     => ucfirst($status),
        };
    }

    public static function urgentPriority(array $camp): string
    {
        if (empty($camp['ends_at'])) {
            return 'priority-medium';
        }
        $days = (new DateTimeImmutable((string) $camp['ends_at']))
            ->diff(new DateTimeImmutable('today'))
            ->days;
        return $days <= 3 ? 'priority-high' : 'priority-medium';
    }

    public static function urgentPriorityLabel(array $camp): string
    {
        return self::urgentPriority($camp) === 'priority-high' ? 'High' : 'Medium';
    }
}
