<?php

/**
 * Mask an email address for PII protection.
 * e.g. "john@example.local" => "j***@example.local"
 *
 * @param string $email
 * @return string
 */
function mask_email(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '***';
    }

    $local  = $parts[0];
    $domain = $parts[1];

    if (strlen($local) <= 1) {
        return $local . '***@' . $domain;
    }

    return substr($local, 0, 1) . '***@' . $domain;
}

/**
 * Mask an IP address for PII protection.
 * e.g. "192.168.1.100" => "192.168.***.***.***"
 *
 * @param string $ip
 * @return string
 */
function mask_ip(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $octets = explode('.', $ip);
        return $octets[0] . '.' . $octets[1] . '.***.' . '***';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $groups = explode(':', $ip);
        $visible = array_slice($groups, 0, 2);
        return implode(':', $visible) . ':***:***:***:***:***:***';
    }

    return '***';
}

/**
 * Mask a user ID for PII protection.
 * e.g. 12345 => "user_***45"
 *
 * @param int|string $id
 * @return string
 */
function mask_user_id($id): string
{
    $str = (string) $id;
    $suffix = strlen($str) >= 2 ? substr($str, -2) : $str;
    return 'user_***' . $suffix;
}

/**
 * Generate a secure API token prefixed with "rc_".
 * Returns the plaintext token (32 bytes = 64 hex chars + prefix).
 *
 * @return string
 */
function generate_token(): string
{
    return 'rc_' . bin2hex(random_bytes(32));
}
