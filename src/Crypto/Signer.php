<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Crypto;

use DateTimeInterface;
use IntrAgile\NavOnlineSzamla\Support\Timestamp;

/**
 * Authentication hashes of the NAV Online Számla v3.0 interface.
 *
 * @see https://github.com/nav-gov-hu/Online-Invoice Interface specification, section 1.5
 */
final class Signer
{
    /**
     * Upper-case SHA-512 hash of the technical user's password.
     */
    public static function passwordHash(string $password): string
    {
        return strtoupper(hash('sha512', $password));
    }

    /**
     * Compute the requestSignature value.
     *
     * Query operations sign requestId + timestamp + sign key only. For manageInvoice and
     * manageAnnulment, pass one entry per index, in index order, holding the operation
     * literal (e.g. CREATE) and the base64 payload.
     *
     * @param  list<array{operation: string, data: string}>  $operations
     */
    public static function requestSignature(
        string $requestId,
        DateTimeInterface $timestamp,
        string $signKey,
        array $operations = [],
    ): string {
        $base = $requestId.Timestamp::signatureMask($timestamp).$signKey;

        foreach ($operations as $operation) {
            $base .= self::sha3($operation['operation'].$operation['data']);
        }

        return self::sha3($base);
    }

    /**
     * Hash of an electronic invoice file (electronicInvoiceHash).
     *
     * NAV only accepts SHA3-512 when completenessIndicator is true.
     */
    public static function electronicInvoiceHash(string $content, HashAlgorithm $algorithm = HashAlgorithm::Sha3_512): string
    {
        return strtoupper(hash($algorithm->phpName(), $content));
    }

    private static function sha3(string $value): string
    {
        return strtoupper(hash('sha3-512', $value));
    }
}
