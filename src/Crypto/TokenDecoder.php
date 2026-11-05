<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Crypto;

use IntrAgile\NavOnlineSzamla\Exceptions\TokenDecodingException;

/**
 * AES-128-ECB decoding of the encodedExchangeToken returned by tokenExchange.
 */
final class TokenDecoder
{
    private const CIPHER = 'aes-128-ecb';

    public static function decode(string $encodedToken, string $exchangeKey): string
    {
        if (strlen($exchangeKey) !== 16) {
            throw new TokenDecodingException('The exchange key must be exactly 16 bytes (AES-128).');
        }

        $cipherText = base64_decode($encodedToken, true);
        if ($cipherText === false || $cipherText === '') {
            throw new TokenDecodingException('The encoded exchange token is not valid base64.');
        }

        $token = openssl_decrypt($cipherText, self::CIPHER, $exchangeKey, OPENSSL_RAW_DATA);
        if ($token === false || $token === '') {
            throw new TokenDecodingException('The exchange token cannot be decoded with the configured exchange key.');
        }

        return $token;
    }

    /**
     * Inverse of decode(); used by test doubles.
     */
    public static function encode(string $token, string $exchangeKey): string
    {
        $cipherText = openssl_encrypt($token, self::CIPHER, $exchangeKey, OPENSSL_RAW_DATA);
        if ($cipherText === false) {
            throw new TokenDecodingException('The exchange token cannot be encoded.');
        }

        return base64_encode($cipherText);
    }
}
