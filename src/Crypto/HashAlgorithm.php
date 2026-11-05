<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Crypto;

/**
 * Values of the NAV cryptoType attribute.
 */
enum HashAlgorithm: string
{
    case Sha512 = 'SHA-512';
    case Sha3_512 = 'SHA3-512';
    case Sha256 = 'SHA-256';

    public function phpName(): string
    {
        return match ($this) {
            self::Sha512 => 'sha512',
            self::Sha3_512 => 'sha3-512',
            self::Sha256 => 'sha256',
        };
    }
}
