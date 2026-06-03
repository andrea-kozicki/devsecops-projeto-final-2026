<?php
declare(strict_types=1);

function generateRandomHexToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function hashOpaqueToken(string $plainToken): string
{
    return hash('sha256', $plainToken);
}

function generateBase32Secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';

    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $secret;
}

function base32Decode(string $input): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
    $bits = '';
    $output = '';

    for ($i = 0, $len = strlen($input); $i < $len; $i++) {
        $val = strpos($alphabet, $input[$i]);
        if ($val === false) {
            continue;
        }
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
        $output .= chr(bindec(substr($bits, $i, 8)));
    }

    return $output;
}

function totpCode(string $secret, ?int $time = null, int $digits = 6, int $period = 30): string
{
    $time ??= time();
    $counter = intdiv($time, $period);
    $binarySecret = base32Decode($secret);
    $counterBytes = pack('N*', 0) . pack('N*', $counter);

    $hash = hash_hmac('sha256', $counterBytes, $binarySecret, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $chunk = substr($hash, $offset, 4);
    $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;
    $mod = 10 ** $digits;

    return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code, int $window = 1): bool
{
    $code = trim($code);

    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totpCode($secret, $now + ($i * 30)), $code)) {
            return true;
        }
    }

    return false;
}

function buildOtpAuthUri(string $email, string $secret, string $issuer = 'StudyBoard'): string
{
    $label = rawurlencode($issuer . ':' . $email);
    $issuerEncoded = rawurlencode($issuer);

    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEncoded}&algorithm=SHA256&digits=6&period=30";
}