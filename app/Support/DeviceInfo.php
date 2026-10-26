<?php

namespace App\Support;

class DeviceInfo
{
    public static function mac(?string $ip): ?string
    {
        if (! $ip || $ip === '127.0.0.1' || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        try {
            $output = @shell_exec('arp -a ' . escapeshellarg($ip));

            if ($output && preg_match('/([0-9a-f]{2}[-:]){5}[0-9a-f]{2}/i', $output, $m)) {
                return strtolower(str_replace('-', ':', $m[0]));
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    public static function type(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) return 'tablet';
        if (str_contains($ua, 'iphone')) return 'phone';
        if (str_contains($ua, 'android')) {
            return str_contains($ua, 'mobile') ? 'phone' : 'tablet';
        }
        if (str_contains($ua, 'windows') || str_contains($ua, 'macintosh')
            || str_contains($ua, 'linux') || str_contains($ua, 'cros')) {
            return 'computer';
        }

        return 'unknown';
    }
}
