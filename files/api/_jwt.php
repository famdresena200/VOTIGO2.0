<?php
declare(strict_types=1);

/**
 * Simple JWT implementation for VOTIGO API
 * Not using external libraries to keep it lightweight
 */

class JWT {
    private static string $algorithm = 'HS256';
    
    public static function getSecret(): string {
        return (string)($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-jwt-secret');
    }
    
    public static function encode(array $payload, int $expiresIn = 86400): string {
        $header = self::base64UrlEncode(json_encode([
            'alg' => self::$algorithm,
            'typ' => 'JWT'
        ]));
        
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $expiresIn;
        
        $payload = self::base64UrlEncode(json_encode($payload));
        
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::getSecret(), true)
        );
        
        return "$header.$payload.$signature";
    }
    
    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        [$header, $payload, $signature] = $parts;
        
        // Verify signature
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::getSecret(), true)
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }
        
        // Decode and verify payload
        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decoded)) {
            return null;
        }
        
        // Check expiration
        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            return null;
        }
        
        return $decoded;
    }
    
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 4 - (strlen($data) % 4)));
    }
}
