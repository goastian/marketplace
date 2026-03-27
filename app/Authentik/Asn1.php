<?php

namespace App\Authentik;

final class Asn1
{
    public static function encodeLength(int $length): string
    {
        if ($length <= 0x7F) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    public static function encodeInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::encodeLength(strlen($bytes)).$bytes;
    }

    public static function encodeSequence(string $bytes): string
    {
        return "\x30".self::encodeLength(strlen($bytes)).$bytes;
    }

    public static function encodeBitString(string $bytes): string
    {
        return "\x03".self::encodeLength(strlen($bytes) + 1)."\x00".$bytes;
    }

    public static function encodeNull(): string
    {
        return "\x05\x00";
    }

    public static function encodeOid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid OID.');
        }

        $first = (40 * $parts[0]) + $parts[1];
        $result = chr($first);

        foreach (array_slice($parts, 2) as $part) {
            $encoded = '';
            do {
                $encoded = chr(($part & 0x7F) | ($encoded === '' ? 0x00 : 0x80)).$encoded;
                $part >>= 7;
            } while ($part > 0);
            $result .= $encoded;
        }

        return "\x06".self::encodeLength(strlen($result)).$result;
    }
}

