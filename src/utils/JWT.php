<?php

namespace Utils;

class JWT
{
    public static function encode(array $payload, string $secret): string
    {
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        return "$header.$payload.$signature";
    }

    public static function decode(string $jwt, string $secret): ?array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $valid = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        if (!hash_equals($valid, $signature)) {
            return null;
        }
        return json_decode(self::base64UrlDecode($payload), true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
