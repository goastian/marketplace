<?php

namespace App\Authentik;

final class Jwt
{
    public static function decodePart(string $part): array
    {
        $json = Base64Url::decode($part);

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid JWT JSON.');
        }

        return $decoded;
    }

    public static function split(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid JWT format.');
        }

        return $parts;
    }
}

