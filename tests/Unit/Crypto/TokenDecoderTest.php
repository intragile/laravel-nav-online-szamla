<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Unit\Crypto;

use IntrAgile\NavOnlineSzamla\Crypto\TokenDecoder;
use IntrAgile\NavOnlineSzamla\Exceptions\TokenDecodingException;
use PHPUnit\Framework\TestCase;

final class TokenDecoderTest extends TestCase
{
    private const KEY = '1234567890abcdef';

    public function test_known_aes_128_ecb_vector_decodes(): void
    {
        // Generated independently: printf 'hello-nav-token' | openssl enc -aes-128-ecb -K <hex(KEY)> | base64
        $this->assertSame('hello-nav-token', TokenDecoder::decode('vHgaRSAQxT5L4b8hj5ATVA==', self::KEY));
    }

    public function test_round_trip(): void
    {
        $encoded = TokenDecoder::encode('0123456789-abcdef-token', self::KEY);

        $this->assertSame('0123456789-abcdef-token', TokenDecoder::decode($encoded, self::KEY));
    }

    public function test_wrong_key_fails_loudly(): void
    {
        $encoded = TokenDecoder::encode('token', self::KEY);

        $this->expectException(TokenDecodingException::class);
        TokenDecoder::decode($encoded, 'ffffffffffffffff');
    }

    public function test_key_length_is_enforced(): void
    {
        $this->expectException(TokenDecodingException::class);
        TokenDecoder::decode('AAAA', 'short');
    }

    public function test_invalid_base64_is_rejected(): void
    {
        $this->expectException(TokenDecodingException::class);
        TokenDecoder::decode('***', self::KEY);
    }
}
