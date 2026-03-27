<?php

namespace App\Authentik;

final class Jwk
{
    public static function rsaToPublicKeyPem(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA') {
            throw new \InvalidArgumentException('Unsupported JWK kty.');
        }

        $n = Base64Url::decode((string) ($jwk['n'] ?? ''));
        $e = Base64Url::decode((string) ($jwk['e'] ?? ''));

        if ($n === '' || $e === '') {
            throw new \InvalidArgumentException('Invalid RSA JWK.');
        }

        $rsaPublicKey = Asn1::encodeSequence(
            Asn1::encodeInteger($n).Asn1::encodeInteger($e)
        );

        $algorithmIdentifier = Asn1::encodeSequence(
            Asn1::encodeOid('1.2.840.113549.1.1.1').Asn1::encodeNull()
        );

        $subjectPublicKeyInfo = Asn1::encodeSequence(
            $algorithmIdentifier.Asn1::encodeBitString($rsaPublicKey)
        );

        $pemBody = chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n");

        return "-----BEGIN PUBLIC KEY-----\n".$pemBody."-----END PUBLIC KEY-----\n";
    }
}

