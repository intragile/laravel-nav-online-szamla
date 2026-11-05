<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Crypto;

use Intragile\NavOnlineSzamla\Exceptions\TokenDecodingException;

/**
 * A /tokenExchange által adott `encodedExchangeToken` dekódolása a technikai felhasználó
 * cserekulcsával, AES-128-ECB algoritmussal (spec v3.0, 1.6).
 */
final class TokenDecoder
{
    public static function decode(string $encodedToken, string $exchangeKey): string
    {
        if (strlen($exchangeKey) !== 16) {
            throw new TokenDecodingException('A cserekulcsnak pontosan 16 bájtosnak kell lennie (AES-128).');
        }

        $cipherText = base64_decode($encodedToken, true);
        if ($cipherText === false || $cipherText === '') {
            throw new TokenDecodingException('Az encodedExchangeToken nem érvényes base64.');
        }

        $token = openssl_decrypt($cipherText, 'aes-128-ecb', $exchangeKey, OPENSSL_RAW_DATA);
        if ($token === false || $token === '') {
            throw new TokenDecodingException('A token nem dekódolható ezzel a cserekulccsal. Ellenőrizd az NAV_EXCHANGE_KEY értékét.');
        }

        return $token;
    }

    /** A dekódolás inverze; teszteléshez és a fake szerverhez. */
    public static function encode(string $token, string $exchangeKey): string
    {
        $cipherText = openssl_encrypt($token, 'aes-128-ecb', $exchangeKey, OPENSSL_RAW_DATA);
        if ($cipherText === false) {
            throw new TokenDecodingException('A token nem kódolható.');
        }

        return base64_encode($cipherText);
    }
}
